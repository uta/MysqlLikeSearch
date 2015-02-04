<?php
if(!defined('MEDIAWIKI')) die;

class MysqlLikeSearch extends SearchMySQL {
  const ENCODING          = 'UTF-8';
  const EXTENSION         = 'MysqlLikeSearch';
  const META_AND          = '&';
  const META_NOT          = '-';
  const META_OR           = '|';
  const META_PHRASE       = '"';
  const META_SPACE        = ' ';
  const PATTERN_AND       = '/\A(&+|and)\z/i';
  const PATTERN_META_ONLY = '/\A([-&|]+|and|or)\z/i';
  const PATTERN_NOT       = '/\A-(.+)\z/';
  const PATTERN_OR        = '/\A(\|+|or)\z/i';

  public function __construct(DatabaseBase $db = null) {
    mb_regex_encoding(self::ENCODING);
    parent::__construct($db);
  }

  public function filter($str) {
    return $str;
  }

  public function getCountQuery($term, $fulltext) {
    $this->debug('begin');
    $query = array('tables'=>array(), 'fields'=>array(), 'conds'=>array(), 'options'=>array(), 'joins'=>array());
    $this->queryMain($query, $term, $fulltext, true);
    $this->queryFeatures($query);
    $this->queryNamespaces($query);
    $this->debug('$query', $query);
    return $query;
  }

  public function getQuery($term, $fulltext) {
    $this->debug('begin');
    return parent::getQuery($term, $fulltext);
  }

  public function parseQuery($term, $fulltext) {
    $this->debug('begin');
    $this->parseQueryInitialize($term);
    $this->parseQueryToKeywords();
    $this->parseQueryToCondition();
    return $fulltext ? $this->conditionForText : $this->conditionForTitle;
  }
  private $conditonForText, $conditionForTitle, $searchKeywords, $searchRawTerm;
  private function parseQueryInitialize($term) {
    $trimmedTerm = $this->trim($term);
    if($this->searchRawTerm != $trimmedTerm) {
      $this->conditionForText   = null;
      $this->conditionForTitle  = null;
      $this->searchKeywords     = array();
      $this->searchRawTerm      = $trimmedTerm;
      $this->searchTerms        = array();
    }
  }
  private function parseQueryToCondition() {
    if(is_null($this->conditionForText)) {
      $this->conditionForText  = '';
      $this->conditionForTitle = '';
      if($this->searchKeywords['simple']) {
        $term = $this->escape($this->searchKeywords['simple']);
        $this->conditionForText  = "old_text   LIKE '%${term}%'";
        $this->conditionForTitle = "page_title LIKE '%${term}%'";
      } else {
        $ope   = '';
        foreach($this->searchKeywords['plus'] as $v) {
          if(preg_match(self::PATTERN_AND, $v)) {
            $ope = empty($this->conditionForText) ? '' : ' AND ';
            continue;
          }
          if(preg_match(self::PATTERN_OR, $v)) {
            if(!empty($this->conditionForText)) {
              $this->conditionForText  = '(' . $this->conditionForText  . ')';
              $this->conditionForTitle = '(' . $this->conditionForTitle . ')';
              $ope = ' OR ';
            }
            continue;
          }
          $term = $this->escape($v);
          $this->conditionForText  .= "${ope}old_text   LIKE '%${term}%'";
          $this->conditionForTitle .= "${ope}page_title LIKE '%${term}%'";
          $ope = ' AND ';
        }
        if($this->searchKeywords['minus']) {
          if(empty($this->conditionForText)) {
            $this->conditionForText  = array();
            $this->conditionForTitle = array();
          } else {
            $this->conditionForText  = array('(' . $this->conditionForText  . ')');
            $this->conditionForTitle = array('(' . $this->conditionForTitle . ')');
          }
          foreach($this->searchKeywords['minus'] as $v) {
            $term = $this->escape($v);
            array_push($this->conditionForText,  "NOT old_text   LIKE '%${term}%'");
            array_push($this->conditionForTitle, "NOT page_title LIKE '%${term}%'");
          }
          $this->conditionForText  = join(' AND ', $this->conditionForText);
          $this->conditionForTitle = join(' AND ', $this->conditionForTitle);
        }
      }
    }
    if(empty($this->conditionForText)) {
      $this->conditionForText  = 'FALSE';
      $this->conditionForTitle = 'FALSE';
    }
  }
  private function parseQueryToKeywords() {
    if(empty($this->searchKeywords)) {
      $term = $this->searchRawTerm;
      if(preg_match(self::PATTERN_META_ONLY, $term)) {
        $this->searchKeywords = array('simple'=>$term);
        $this->searchTerms    = array(preg_quote($term, '/'));
      } else {
        $plus  = array();
        $minus = array();
        $quote = false;
        $buff  = '';
        $term  = $this->fixSpaces($term);
        for($i=0; $i<mb_strlen($term, self::ENCODING); $i++) {
          $c = mb_substr($term, $i, 1, self::ENCODING);
          switch($c) {
            case self::META_PHRASE:
              $quote = !$quote;
              break;
            case self::META_SPACE:
              if($quote) {
                $buff .= $c;
              } else {
                if(preg_match(self::PATTERN_NOT, $buff)) {
                  array_push($minus, substr($buff, 1));
                } else {
                  array_push($plus,  $buff);
                }
                $buff = '';
              }
              break;
            default:
              $buff .= $c;
              break;
          }
        }
        if(!empty($buff)) {
          if(preg_match(self::PATTERN_NOT, $buff)) {
            array_push($minus, substr($buff, 1));
          } else {
            array_push($plus,  $buff);
          }
        }
        $this->searchKeywords = array('plus'=>$plus, 'minus'=>$minus);
        foreach($plus as $v) {
          if(preg_match(self::PATTERN_AND, $v)) continue;
          if(preg_match(self::PATTERN_OR,  $v)) continue;
          array_push($this->searchTerms, preg_quote($v, '/'));
        }
        if(empty($this->searchTerms)) {
          $this->searchTerms = array('\A\z');
        }
      }
      $this->debug('$this->searchKeywords', $this->searchKeywords);
      $this->debug('$this->searchTerms',    $this->searchTerms);
    }
  }
  private function escape($str) {
    /************************************************
     * TODO escape query for like search
     ************************************************/
    return $str;
  }
  private function fixSpaces($term) {
    $result = array();
    foreach(split(self::META_PHRASE, $term) as $i => $v) {
      array_push($result, ($i%2 == 0) ? mb_ereg_replace('[\0\s]+', ' ', $v) : $v);
    }
    return join(self::META_PHRASE, $result);
  }
  private function trim($str) {
    return mb_ereg_replace('(\A[\0\s]+|[\0\s]+\z)', '', $str);
  }

  public function queryMain(&$query, $filteredTerm, $fulltext, $count=false) {
    $this->debug('begin');
    array_push($query['tables'], 'page', 'revision', 'text');
    if($count) {
      array_push($query['fields'], 'COUNT(*) as c');
    } else {
      array_push($query['fields'], 'page_id', 'page_namespace', 'page_title');
    }
    array_push($query['conds'], 'old_id=rev_text_id', 'rev_page=page_id', 'page_latest=rev_id', $this->parseQuery($filteredTerm, $fulltext));
  }

  public function searchText($term) {
    $this->debug('begin');
    return parent::searchText($term);
  }

  public function searchTitle($term) {
    $this->debug('begin');
    return parent::searchTitle($term);
  }

  private function debug($k, $v='') {
    global $wgDebugLogFile;
    if($wgDebugLogFile) {
      try {
        if($k == 'begin') {
          $trace  = debug_backtrace();
          $caller = $trace[1]['function'];
          $args   = $trace[1]['args'];
          wfDebugLog(self::EXTENSION, substr("==[${caller}]" . str_repeat("=", 60), 0, 60));
          foreach($args as $i => $arg) {
            $this->debug("args[$i]", $arg);
          }
        } else {
          if($v instanceof Exception) {
            wfDebugLog(self::EXTENSION, str_repeat("=", 60) . "\n" . $v->__toString());
            wfDebugLog(self::EXTENSION, str_repeat("=", 60));
            return;
          }
          if(is_array($v))    $v = empty($v) ? 'array()' : var_export($v, true);
          if(is_bool($v))     $v = var_export($v, true);
          if(is_null($v))     $v = var_export($v, true);
          if(is_numeric($v))  $v = (string)$v;
          if(is_object($v))   $v = 'instance of ' . get_class($v);
          if(is_string($v)) {
            wfDebugLog(self::EXTENSION, substr($k . str_repeat(' ', 20), 0, 20) . ": $v");
          }
        }
      } catch(Exception $e) {
        try {
          wfDebugLog(self::EXTENSION, 'An exception was thrown while logging.');
          wfDebugLog(self::EXTENSION, str_repeat("=", 60) . "\n" . $e->__toString());
          wfDebugLog(self::EXTENSION, str_repeat("=", 60));
        } catch(Exception $e) {}
      }
    }
  }
}
