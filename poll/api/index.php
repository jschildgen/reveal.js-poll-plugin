<?php
session_start();
header("Content-Type: text/json");

$DB = new PDO('sqlite:./poll.db');
$DB->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// automatically deactivate questions after 15 minutes
$DB->exec("UPDATE questions SET active = 0 WHERE active = 1 AND (julianday(CURRENT_TIMESTAMP)-julianday(qtime))*24*60 > 15");

function cleanup_db() {
  global $DB;
  $DB->exec("DROP TABLE IF EXISTS questions");
  $DB->exec("DROP TABLE IF EXISTS answers");
  $DB->exec("DROP TABLE IF EXISTS responses");
}

function init_db() {
  global $DB; 
  $DB->exec("CREATE TABLE IF NOT EXISTS questions (qid INTEGER PRIMARY KEY AUTOINCREMENT, qtime TIMESTAMP DEFAULT CURRENT_TIMESTAMP, question VARCHAR(2000), active BOOLEAN DEFAULT 1)");
  $DB->exec("CREATE TABLE IF NOT EXISTS answers (qid INTEGER, aid INTEGER, answer VARCHAR(2000), correct BOOLEAN, PRIMARY KEY(qid, aid))");
  $DB->exec("CREATE TABLE IF NOT EXISTS responses (rid INTEGER PRIMARY KEY AUTOINCREMENT, qid INTEGER, aid INTEGER, rtime TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
}

function auth($token) {
  return array("OK" => true);
}

function start_poll($question, $answers, $correct_answers) {
  global $DB;
  stop_poll();
  $stmt = $DB->prepare("INSERT INTO questions (question) VALUES (?)");
  $stmt->execute(array($question));
  $qid = $DB->lastInsertId();

  $stmt = $DB->prepare("INSERT INTO answers (qid, aid, answer, correct) VALUES (?, ?, ?, ?)");

  $aid=0;
  foreach($answers as $answer) {
    $stmt->execute(array($qid, $aid, $answer, in_array($aid, $correct_answers)));
    $aid++;
  }
  return array("OK" => true);
}

function stop_poll() {
  global $DB;
  $stmt = $DB->prepare("SELECT r.aid, COUNT(*) as cnt FROM questions q JOIN responses r ON q.qid=r.qid WHERE q.active = 1 GROUP BY r.aid");
  $stmt->execute(array());
  $res = array("answers"=>array());
  while ($row = $stmt->fetch()) {
    $res["answers"][$row["aid"]] = intval($row["cnt"]);
  }
  $DB->exec("UPDATE questions SET active = 0 WHERE active = 1");
  return $res;
}

function status() {
  global $DB;
  $stmt = $DB->prepare("SELECT 1 FROM questions q WHERE q.active = 1");
  $stmt->execute(array());
  if (!($row = $stmt->fetch())) {
    return (object)array();
  }

  $stmt = $DB->prepare("SELECT COUNT(*) as cnt FROM questions q JOIN responses r ON q.qid=r.qid WHERE q.active = 1");
  $stmt->execute(array());
  $res = array("count"=>0);
  while ($row = $stmt->fetch()) {
    $res["count"] = intval($row["cnt"]);
  }
  return $res;
}

function get_poll() {
  global $DB;
  $stmt = $DB->prepare("SELECT q.qid, q.question, a.aid, a.answer FROM questions q JOIN answers a ON q.qid=a.qid WHERE q.active = 1 ORDER BY q.qid, a.aid");
  $stmt->execute(array());

  $res = array("question" => "", "answers" => array());
  $found = false;
  while ($row = $stmt->fetch()) {
    $found = true;
    $res["qid"] = $row["qid"];
    $res["question"] = $row["question"];
    $res["answers"][$row["aid"]] = $row["answer"];
  }
  if(!$found) { return (object)array(); }
  return $res;
}

function respond($aid) { 
  global $DB; 

  $stmt = $DB->prepare("select q.qid FROM questions q JOIN answers a ON q.qid=a.qid WHERE q.active=1 AND a.aid=?");
  $stmt->execute(array($aid));
  if ($row = $stmt->fetch()) {
    $qid = $row["qid"];
  } else {
    return array("OK" => false);
  }

  if(@$_SESSION['qid'] == $qid) { return array("OK" => false); } /* already voted for that question */

  $_SESSION['qid'] = $qid;

  $stmt = $DB->prepare("INSERT INTO responses (qid, aid) VALUES (?, ?)");
  $stmt->execute(array($qid, $aid));
  return array("OK" => true);
}

//cleanup_db();
init_db();
//start_poll("What is 5+5?", array("5","10", "15","25"), array(1));
//stop_poll();
//print_r(get_poll());
//respond(2);

if(!isset($_GET["method"])) { die("No method"); }
$data = isset($_GET["data"]) ? json_decode($_GET["data"]) : array();

switch($_GET["method"]) {
  case "auth": echo json_encode(auth(@$_GET["token"])); break;
  case "start_poll": echo json_encode(start_poll(@$data->question, @$data->answers, $data->correct_answers)); break;
  case "stop_poll": echo json_encode(stop_poll()); break;
  case "get_poll": echo json_encode(get_poll()); break;
  case "respond": echo json_encode(respond(@$_GET["aid"])); break;
  case "status": echo json_encode(status()); break;
}