<?php
/**
 * Short description for class
 *
 * Long description for class (if any)...
 *
 * @category   API
 * @author     Žiga Šebenik (ziga@sebenik.com)
 * @copyright  Copyright (c) 2016 Žiga Šebenik (ziga@sebenik.com)
 * @license    https://github.com/sebenik/sistat-api/blob/master/LICENSE MIT License 
 * @version    1.0 
 * @link       https://github.com/sebenik/sistat-api
 */

namespace sebenik;

use Z38\PcAxis\Px;
use RuntimeException;
use DomDocument;
use DOMXPath;


class sistat
{

  /**
   * @var string
   */
  private $url;

  /**
   * @var string
   */
  private $pageHTML;

  /**
   * @var array
   */
  private $siteSpecificPostData;

  /**
   * @var array
   */
  private $fieldNames;

  /**
   * @var array
   */
  private $fieldOptions;

  /**
   * @var array
   */
  private $fieldOptionsCount;

  /**
   * @var array
   */
  private $httpHeaders;

  /**
   * @var array
   */
  private $requestFields;

  /**
   * @var string
   */
  private $postData;

  /**
   * @var string
   */
  private $tmpPxFilePath;

  /**
   * @var string
   */
  private $response;


  /**
   * Constructor
   *
   * @param array $requestFields  
   *
   * @throws \RuntimeException when cURL extension is not installed/enabled
   *         or incorrect parameters are provided
   */
  function __construct(array $getParams) {
    if(!in_array("curl", get_loaded_extensions())){
      throw new RuntimeException(
        "Error: php-curl extension not installed/enabled.",
        500
      );
    }

    if(!(isset($getParams["ma"]) && isset($getParams["path"]))) {
      throw new RuntimeException(
        "Error: 'ma' and 'path' parameters must be present in your API call.",
        400
      );
    }

    $this->curl = curl_init();
    $this->url = "http://pxweb.stat.si/pxweb/Dialog/varval.asp?".
      "path=".$getParams["path"].
      "&ma=".$getParams["ma"];
    $this->getParams = $getParams;

    unset($getParams["ma"]);
    unset($getParams["path"]);

    $this->requestFields = array_map(function($el) {
      return explode(",", $el);
    }, $getParams);

    $this->makeApiCall();
  }

  /**
   * Makes calls to other private fucntions
   */
  public function makeApiCall() {
    $this->createHttpHeaders();
    $this->fetchPageHTML();
    $this->getSiteSpecificPostData();
    $this->getFieldNames();
    $this->countFieldOptions();
    $this->getFieldOptions();
    $this->buildPostDataArray();
    $this->getPxFile();
    $this->parsePxFile();
  }

  public function getResponse() {
    return $this->response;
  }

  /**
   * Creates HTTP headers array, seting current url for the
   * Referer header
   */
  private function createHttpHeaders() {
    $this->httpHeaders = array(
      "Origin: http://pxweb.stat.si",
      "Accept-Encoding: gzip, deflate",
      "Accept-Language: en-US,en;q=0.8",
      "Upgrade-Insecure-Requests: 1",
      "Content-Type: application/x-www-form-urlencoded",
      "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8",
      "Cache-Control: max-age=0",
      "Referer: $this->url",
      "Connection: keep-alive"
    );
  }

  /**
   * Fetches HTML of a webpage app for current API call
   *
   * @throws \RuntimeException when page HTML can't be fetched
   */
  private function fetchPageHTML() {
    curl_setopt($this->curl, CURLOPT_URL, $this->url);
    curl_setopt($this->curl, CURLOPT_ENCODING , "");
    curl_setopt($this->curl, CURLOPT_FAILONERROR, true);
    curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($this->curl, CURLOPT_CONNECTTIMEOUT , 10);
    curl_setopt($this->curl, CURLOPT_TIMEOUT, 10); //timeout in seconds

    $this->pageHTML = curl_exec($this->curl);

    $statusCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
    if(curl_error($this->curl) || $statusCode === 0) {
      throw new RuntimeException(
        "Error: ".curl_error($this->curl),
        408
      );
    }
    if(curl_error($this->curl) || $statusCode !== 200) {
      throw new RuntimeException(
        "Error: ".curl_error($this->curl),
        $statusCode
      );
    }
  }

  /**
   * Get and save all field names available on a webpage app for
   * current API call
   */
  private function getFieldNames() {
    $doc = new DomDocument;
    $doc->loadHTML($this->pageHTML);
    $xpath = new DOMXPath($doc);

    $fields = $xpath->query("//*[@class='table_NewLook']//input[@type='hidden'][contains(@name, 'var')]");

    foreach($fields as $f) {
      $fieldName = $f->getAttribute("value");
      $fieldNumber = preg_replace("/^var/", "", $f->getAttribute("name"));

      $this->fieldNames[$fieldNumber] = $fieldName;
    }
  }

  /**
   * Count number of options for each field name on a webpage app
   * for current API call
   */
  private function countFieldOptions() {
    $doc = new DomDocument;
    $doc->loadHTML($this->pageHTML);
    foreach($this->fieldNames as $key => $value) {
      $select = $doc->getElementById("values".$key);
      $options = $select->getElementsByTagName("option");
      $this->fieldOptionsCount[$key] = $options->length;
    }
  }

  /**
   * Get and save all field option values for each field name on a
   * webpage app for current API call
   */
  private function getFieldOptions() {
    $doc = new DomDocument;
    $doc->loadHTML($this->pageHTML);
    $xpath = new DOMXPath($doc);

    foreach($this->fieldNames as $key => $value) {
      $this->fieldOptions[$key] = array();
      foreach($this->requestFields[$this->toLowercaseAndSpaceToUnderscore($value)] as $rf) {
        if(strcasecmp($rf, "all") === 0) {
          $this->fieldOptions[$key] = range(1, $this->fieldOptionsCount[$key]);
        } elseif (strcasecmp($rf, "last") === 0) {
          $this->fieldOptions[$key] = array($this->fieldOptionsCount[$key]);
        } elseif (strcasecmp($rf, "first") === 0) {
          $this->fieldOptions[$key] = array(1);
        } else {
          $options = $xpath->query("//select[@id='values{$key}']//option[text()[normalize-space(.) = '$rf']]");
          foreach($options as $o) {
            $this->fieldOptions[$key][] = $o->getAttribute("value");
          }
        }
      }
    }
  }

  /**
   * Get and save other reuqired API call data from the webpage app
   * for current API call
   *
   * @throws \RuntimeException if 'elim' element is not found
   */
  private function getSiteSpecificPostData() {
    $doc = new DomDocument;
    $doc->loadHTML($this->pageHTML);
    $xpath = new DOMXPath($doc);

    $elim = $xpath->query("//input[@name='elim']");
    if($elim->length <= 0) {
      throw new RuntimeException(
        "Error: could not find 'elim' input.",
        500
      );
    } else {
      $this->siteSpecificPostData["elim"] = $elim[0]->getAttribute("value");
    }
  }

  /**
   * Build post data string for the current API call
   */
  private function buildPostDataArray() {
    $query = array(
      "pxkonv"   => "px",
      "matrix"   => $this->getParams["ma"],
      "root"     => $this->getParams["path"],
      "classdir" => $this->getParams["path"],
      "noofvar"  => count($this->fieldNames),
      "elim"     => $this->siteSpecificPostData["elim"],
      "lang"     => 2,
    );
    $this->postData = http_build_query($query);

    for($i = 1; $i <= count($this->fieldNames); $i++) {
      $query = array(
        "context".$i => "",
        "var".$i => $this->fieldNames[$i],
        "Valdavarden".$i => count($this->fieldOptions[$i])
      );
      $this->postData .= "&".http_build_query($query);

      asort($this->fieldOptions[$i]);
      foreach($this->fieldOptions[$i] as $fo) {
        $this->postData .= "&".http_build_query(array("values".$i => $fo));
      }
    }
  }

  /*
   * Download and save PC-Axis file for current API call
   *
   * @throws \RuntimeException when response code != 200 or response != PC-axis file
   */
  public function getPxFile() {
    curl_setopt($this->curl, CURLOPT_URL, "http://pxweb.stat.si/pxweb/Dialog/Saveshow.asp");
    curl_setopt($this->curl, CURLOPT_ENCODING , "");
    curl_setopt($this->curl, CURLOPT_FAILONERROR, true);
    curl_setopt($this->curl, CURLOPT_POST, true);
    curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true); 
    curl_setopt($this->curl, CURLOPT_HTTPHEADER, $this->httpHeaders);
    curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->postData);

    $pxContent = curl_exec($this->curl);

    $statusCode = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
    if(curl_error($this->curl) || $statusCode  !== 200) {
      throw new RuntimeException(
        "Error: ".curl_error($this->curl),
        $statusCode
      );
    }

    if(curl_getinfo($this->curl, CURLINFO_CONTENT_TYPE) !== "text/x-pcaxis") {
      throw new RuntimeException(
        "Error: could not retrieve PC-Axis file. Check your request parameters and confrm from '$this->url' that you can download your requesrt in PC-Axis file format.",
        409
      );
    }

    // convert from ANSI to UTF-8 (to preserve čšž chars)
    $pxContent = iconv("windows-1250", "utf-8", $pxContent);
    // correct CHARSET tag in px file to reflect new encoding
    $pos = strpos($pxContent, "ANSI");
    if ($pos !== false) {
      $pxContent = substr_replace($pxContent, "UTF-8", $pos, strlen("ANSI"));
    }

    $this->tmpPxFilePath = tempnam(sys_get_temp_dir(), "sistat_px_");
    file_put_contents($this->tmpPxFilePath, $pxContent);
  }

  /*
   * Parse PC-Axis file and save it in such an array structure that corresponds with
   * JSON-STAT format (https://json-stat.org/) when converting this array to JSON
   * with json_encode() function.
   */
  private function parsePxFile() {
    $px = new PX($this->tmpPxFilePath);

    $variables = $px->variables(); // returns a list of all variables in pc-axis file
    $codes = array();
    $values = array();
    $valuesSize = array();
    $dimension = array();

    // get arrays with keynames from pc-axis file
    $pxNote = $px->keywordList("NOTE");
    $pxNotex = $px->keywordList("NOTEX");
    $pxValuenote = $px->keywordList("VALUENOTE");
    $pxValuenotex = $px->keywordList("VALUENOTEX");

    // populate dimension array
    foreach($variables as $var) {
      $c = $px->codes($var);
      $v = $px->values($var);
      $cV = count($v);
      $codes[] =  $c;
      $values[] = $v;
      $valuesSize[] = $cV;

      // if exist note with variable name, append it to dimension->variable->note array
      if($variableNote = $this->findNotex($var, $pxNote)) {
        $dimension[$this->toLowercaseAndSpaceToUnderscore($var)]["note"][] = $variableNote;
      }
      // if exist notex with variable name, append it to dimension->variable->note array
      if($variableNotex = $this->findNotex($var, $pxNotex)) {
        $dimension[$this->toLowercaseAndSpaceToUnderscore($var)]["note"][] = $variableNotex;
      }

      // init empty category child arrays
      $categoryIndex = array();
      $categoryLabel = array();
      $categoryNote = array();
      for($i = 0; $i < $cV; $i++) {
        $categoryIndex["i-".$c[$i]] = $i;
        $categoryLabel["i-".$c[$i]] = $v[$i];
        // if exist 'valuenote' or 'valunotex' with variable & category label name, construct array of category valuenotes
        // (categorNote[index] = [valunotes])
        if($pxVnx = $this->findValuenotex($var, $v[$i], $pxValuenote)) {
          $categoryNote["i-".$c[$i]][] = $pxVnx;
        }
        if($pxVnx = $this->findValuenotex($var, $v[$i], $pxValuenotex)) {
          $categoryNote["i-".$c[$i]][] = $pxVnx;
        }
      }

      // append '.' to index and label array keys if keys are mixed values (numeric and string)
      //      $filteredCategoryIndex = array_filter($categoryIndex, 'is_numeric', ARRAY_FILTER_USE_KEY);
      //      if(!empty($filteredCategoryIndex) && count($filteredCategoryIndex) !== count($categoryIndex))
      //      {
      //        for($i = 0; $i < $cV; $i++) {
      //          $newCategoryIndex[$c[$i]."."] = $categoryIndex[$c[$i]];
      //          $newCategoryLabel[$c[$i]."."] = $categoryLabel[$c[$i]];
      //        }
      //        $categoryIndex = $newCategoryIndex;
      //        $categoryLabel = $newCategoryLabel;
      //      }

      // append label and category arrays to dimension array
      $dimension[$this->toLowercaseAndSpaceToUnderscore($var)] = array(
        "label" => $var,
        "category" => array(
          "index" => (object)$categoryIndex,
          "label" => (object)$categoryLabel
        )
      );
      // append category->note array if not empty
      if(!empty($categoryNote)) {
        $dimension[$this->toLowercaseAndSpaceToUnderscore($var)]["category"]["note"] = $categoryNote;
      }
    }

    // get data values from px file and replace dots (no data) with null
    $data = array_map(array($this, "dotsToNull"), $px->data());

    // append all remaining notes, notex, valuenote, valunetox
    // (those that didn't match with variable & category label names) to root->note array
    $note = array();
    $allRemainingNotes = array_merge($pxNote, $pxnotex, $pxValuenote, $pxValuenotex);
    foreach($allRemainingNotes as $arn) {
      if(empty($arn->subKeys)) {
        $note[] = implode(" ", $arn->values);
      } else {
        $note[] = $this->toLowercaseAndSpaceToUnderscore(implode("-", $arn->subKeys)).": ".implode(" ", $arn->values);
      }
    }

    $responseArray["version"] = "2.0";
    $responseArray["class"] = "dataset";
    if($px->hasKeyword("TITLE")) {
      $pxTitle = $px->keyword("TITLE");
      $responseArray["title"] = $pxTitle->values[0];
    }
    if($px->hasKeyword("DESCRIPTION")) {
      $pxDescription = $px->keyword("DESCRIPTION");
      $responseArray["description"] = $pxDescription->values[0];
    }
    if($px->hasKeyword("CONTENTS")) {
      $pxContents = $px->keyword("CONTENTS");
      $responseArray["contents"] = $pxContents->values[0];
    }
    if($px->hasKeyword("CREATION-DATE")) {
      $pxCreated = $px->keyword("CREATION-DATE");
      $responseArray["created"] = $pxCreated->values[0];
    }
    if($px->hasKeyword("LAST-UPDATED")) {
      $pxUpdated = $px->keyword("LAST-UPDATED");
      $responseArray["updated"] = $pxUpdated->values[0];
    }
    if($px->hasKeyword("SOURCE")) {
      $pxSource = $px->keyword("SOURCE");
      $responseArray["source"] = $pxSource->values[0];
    }
    $responseArray["href"] = $this->url;
    $responseArray["id"] = array_map(array($this, "toLowercaseAndSpaceToUnderscore"), $variables);
    $responseArray["size"] = $valuesSize;
    $responseArray["dimension"] = $dimension;
    $responseArray["value"] = (object)$data;
    if(!empty($note)) {
      $responseArray["note"] = $note;
    }

    $this->response = $responseArray;
  }

  /**
   * Replaces all instances of space with a single underscore and
   * converts all characters within a string to lowercase
   *
   * @param string $str
   *
   * @return formated string
   */
  private function toLowercaseAndSpaceToUnderscore($str) {
    return mb_strtolower(preg_replace("/\s+/", "_", $str), "UTF-8");
  }

  /**
   * Check if string matches Pc-axis string format idicating missing
   * value and returns null if true 
   *
   * @param string $str
   *
   * @return string
   * @return null (if $str matches any value in dotsArray)
   */
  private function dotsToNull($str) {
    $dotsArray = array(".", "..", "...", "....", ".....", "......");
    return in_array($str, $dotsArray) ? null : $str;
  }

  /**
   * Find if exists a note in $pxValuenotex array where first subkey
   * equals $variable and second subkey equals label. If so, return
   * its value and unset this key from $pxValuenotex array.
   *
   * @param string $variable
   * @param string $label
   * @param array $pxValuenotex
   *
   * @return string | false
   */
  private function findValuenotex($variable, $label, &$pxValuenotex) {
    $valuenotex = "";
    foreach($pxValuenotex as $vnxKey => $vnxValue) {
      $subKeys = $pxValuenotex[$vnxKey]->subKeys;
      if(count($subKeys) > 1
        && $subKeys[0] == $variable &&
        $this->toLowercaseAndSpaceToUnderscore($subKeys[1]) == $this->toLowercaseAndSpaceToUnderscore($label))
      {
        $valuenotex .= implode(" ", $pxValuenotex[$vnxKey]->values);
        unset($pxValuenotex[$vnxKey]);
      }
    }
    return empty($valuenotex) ? false : $valuenotex;
  }

  /**
   * Find if exists a note in $pxNotex array where first subkey
   * equals $variable. If so, return its value and unset this key
   * from $pxNotex array.
   *
   * @param string $variable
   * @param array $pxNotex
   *
   * @return string | false
   */
  private function findNotex($variable, &$pxNotex) {
    $notex = "";
    foreach($pxNotex as $nxKey => $nxValue) {
      $subKeys = $pxNotex[$nxKey]->subKeys;
      if(empty($subKeys)) {
        continue;
      } elseif($subKeys[0] == $variable) {
        $notex .= implode(" ", $pxNotex[$nxKey]->values);
        unset($pxNotex[$nxKey]);
      }
    }
    return empty($notex) ? false : $notex;
  }

}

