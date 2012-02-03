<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once(dirname(dirname(__FILE__)) . '/../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/plagiarismlib.php');
require_once($CFG->dirroot.'/plagiarism/turnitin/lib.php');
require_once('turnitin_form.php');

require_login();
admin_externalpage_setup('plagiarismturnitin');

$id = optional_param('id',0, PARAM_INT);
$clear = optional_param('clear', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);
$sort = optional_param('sort', '', PARAM_ALPHA);
//$dir = optional_param('dir', '', PARAM_ALPHA);

if ($clear == 1 and $id) {
    $returnurl = new moodle_url('turnitin_events.php');
    $continueurl = new moodle_url('turnitin_events.php', array('id'=>$id, 'clear'=>1, 'confirm'=>1));
    if (!empty($confirm) and confirm_sesskey()) { 
        $qhandler = $DB->get_record('events_queue_handlers', array('id'=>$id), '*', MUST_EXIST);
        //$DB->delete_records('events_queue', array('id'=>$qhandler->queuedeventid));
        $DB->delete_records('events_queue_handlers', array('id'=>$id));
        redirect($returnurl, get_string('eventdeleted','plagiarism_turnitin'));
    }
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(get_string('deletequeuedevent', 'plagiarism_turnitin', $id), $continueurl, $returnurl);
    echo $OUTPUT->footer();
    die();
}


$limit = 20;
$baseurl = new moodle_url('turnitin_events.php', array('page'=>$page, 'sort'=>$sort));

echo $OUTPUT->header();
$currenttab='turnitinevents';
require_once('turnitin_tabs.php');
require_once($CFG->dirroot.'/plagiarism/turnitin/db/events.php');

$tiihanderfunctions = array();
foreach ($handlers as $handler) {
    $tiihanderfunctions[] = $handler['handlerfunction'];
}

$tiieventnames = array_keys($handlers);
list($insql, $inparams) = $DB->get_in_or_equal($tiieventnames, SQL_PARAMS_NAMED, 'eventname');

$tiisql = 
"SELECT qh.id, h.eventname, qh.status, q.eventdata, q.timecreated 
FROM {events_queue_handlers} qh, {events_handlers} h, {events_queue} q 
WHERE qh.handlerid = h.id 
AND qh.queuedeventid = q.id 
AND h.eventname $insql
AND h.component = 'plagiarism_turnitin' 
ORDER BY q.timecreated";

$tiisqlcount =  "SELECT COUNT(*) 
FROM {events_queue_handlers} qh, {events_handlers} h, {events_queue} q 
WHERE qh.handlerid = h.id 
AND qh.queuedeventid = q.id 
AND h.eventname $insql 
AND h.component = 'plagiarism_turnitin'";

$count = $DB->count_records_sql($tiisqlcount, $inparams);
$tiievents = $DB->get_records_sql($tiisql, $inparams, $page*$limit, $limit);

$table = new html_table();
$columns = array('id', 'eventname', 'status', 'eventdata', 'timecreated', '');
$table->head = $columns;
$table->align = array('left', 'left', 'left', 'left', 'left', 'left');
$table->width = "95%";
if ($tiievents) {
    
    foreach ($tiievents as $tiievent) {
        $row = array();
        $row['id'] = $tiievent->id;
        $row['eventname'] = $tiievent->eventname;
        $row['status'] = $tiievent->status;
        $row['eventdata'] = print_r(unserialize(base64_decode($tiievent->eventdata)), true);
        $row['timecreated'] = userdate($tiievent->timecreated);
        $row['clear'] = '&nbsp;';
        if ($tiievent->status > 0) {
            $row['clear'] = '<a href="turnitin_events.php?clear=1&id='.$tiievent->id.'">'.get_string('clear').'</a>';
        }
        $table->data[] = $row;
    }
}

if (!empty($table)) {
    echo html_writer::table($table);
    echo $OUTPUT->paging_bar($count, $page, $limit, $baseurl);
}

echo $OUTPUT->footer();
