<?php

/* ========================================================================
 * Open eClass 3.0
 * E-learning and Course Management System
 * ========================================================================
 * Copyright 2003-2012  Greek Universities Network - GUnet
 * A full copyright notice can be read in "/info/copyright.txt".
 * For a full list of contributors, see "credits.txt".
 *
 * Open eClass is an open platform distributed in the hope that it will
 * be useful (without any warranty), under the terms of the GNU (General
 * Public License) as published by the Free Software Foundation.
 * The full license can be read in "/info/license/license_gpl.txt".
 *
 * Contact address: GUnet Asynchronous eLearning Group,
 *                  Network Operations Center, University of Athens,
 *                  Panepistimiopolis Ilissia, 15784, Athens, Greece
 *                  e-mail: info@openeclass.org
 * ======================================================================== */


/* ===========================================================================
  modules_pool.php
  @last update: 29-08-2009 by Thanos Kyritsis
  @authors list: Thanos Kyritsis <atkyritsis@upnet.gr>

  based on Claroline version 1.7 licensed under GPL
  copyright (c) 2001, 2006 Universite catholique de Louvain (UCL)

  original file: modules_pool.php Revision: 1.32

  Claroline authors: Piraux Sebastien <pir@cerdecam.be>
  Lederer Guillaume <led@cerdecam.be>
  ==============================================================================
  @Description: This is the page where the list of modules of the course
  present on the platform can be browsed
  user allowed to edit the course can
  delete the modules form this page

  @Comments:

  @todo:
  ==============================================================================
 */

$require_current_course = TRUE;
$require_editor = TRUE;

require_once '../../include/baseTheme.php';
require_once "include/lib/learnPathLib.inc.php";
require_once 'include/lib/modalboxhelper.class.php';
require_once 'include/lib/multimediahelper.class.php';
ModalBoxHelper::loadModalBox();

$body_action = '';
$head_content .= "
<script type='text/javascript'>
function confirmation(name) {
        if (confirm(\"" . clean_str_for_javascript($langAreYouSureDeleteModule) . " \"+ name)) {
                return true;
        } else {
                return false;
        }
}
</script>";

$navigation[] = array('url' => "index.php?course=$course_code", 'name' => $langLearningPaths);
$nameTools = $langLearningObjectsInUse;

// display use explication text
$tool_content .= "<p>$langUseOfPool</p><br />";

// HANDLE COMMANDS:
$cmd = ( isset($_REQUEST['cmd']) && is_string($_REQUEST['cmd']) ) ? (string) $_REQUEST['cmd'] : '';

switch ($cmd) {
    // MODULE DELETE
    case "eraseModule" :
        if (isset($_GET['cmdid']) && is_numeric($_GET['cmdid'])) {
            // used to physically delete the module  from server
            require_once "include/lib/fileManageLib.inc.php";

            $moduleDir = "/courses/" . $course_code . "/modules";
            $moduleWorkDir = $webDir . $moduleDir;

            // delete all assets of this module
            Database::get()->query("DELETE FROM `lp_asset` WHERE `module_id` = ?d", $_GET['cmdid']);

            // delete from all learning path of this course but keep there id before
            $result = Database::get()->queryArray("SELECT * FROM `lp_rel_learnPath_module` WHERE `module_id` = ?d", $_GET['cmdid']);

            Database::get()->query("DELETE FROM `lp_rel_learnPath_module` WHERE `module_id` = ?d", $_GET['cmdid']);

            // delete the module in modules table
            Database::get()->query("DELETE FROM `lp_module`
				WHERE `module_id` = ?d
				AND `course_id` = ?d", $_GET['cmdid'], $course_id);

            //delete all user progression concerning this module
            $sql = "DELETE FROM `lp_user_module_progress` WHERE 1=0 ";

            foreach ($result as $list) {
                $sql .= " OR `learnPath_module_id`=" . intval($list['learnPath_module_id']);
            }
            Database::get()->query($sql);

            // delete directory and it content
            claro_delete_file($moduleWorkDir . "/module_" . (int) $_GET['cmdid']);
        }
        break;

    // COMMAND RENAME :
    //display the form to enter new name
    case "rqRename" :
        if (isset($_GET['module_id']) && is_numeric($_GET['module_id'])) {
            //get current name from DB
            $list = Database::get()->querySingle("SELECT `name` FROM `lp_module`
				WHERE `module_id` = ?d
				AND `course_id` = ?d", $_GET['module_id'], $course_id);

            $tool_content .= disp_message_box("
                        <form method='post' name='rename' action='$_SERVER[SCRIPT_NAME]?course=$course_code'>

                        <table width='100%' class='tbl'>
                        <tr>
                        <td class=\"odd\" width=\"160\"><label for=\"newName\">" . $langInsertNewModuleName . "</label> :</td>
                        <td><input type=\"text\" size=\"40\" name=\"newName\" id=\"newName\" value=\"" . q($list->name) . "\"></input>
                                <input type=\"submit\" value=\"" . $langImport . "\" name=\"submit\">
                                <input type=\"hidden\" name=\"cmd\" value=\"exRename\">
                                <input type=\"hidden\" name=\"module_id\" value=\"" . (int) $_GET['module_id'] . "\">
                        </td>
                        </tr>
                        </table>
                        </form>
                        <br />") . "";
        }
        break;

    //try to change name for selected module
    case "exRename" :
        //check if newname is empty
        if (isset($_POST["newName"]) && is_string($_POST["newName"]) && $_POST["newName"] != "" && isset($_POST['module_id']) && is_numeric($_POST['module_id'])) {
            //check if newname is not already used in another module of the same course
            $num = Database::get()->querySingle("SELECT COUNT(name) AS count
                  FROM `lp_module`
                  WHERE `name` = ?s
                    AND `module_id` != ?d
                    AND `course_id` = ?d", $_POST['newName'], $_POST['module_id'], $course_id)->count;
            if ($num == 0) { // "name" doesn't already exist
                // if no error occurred, update module's name in the database
                Database::get()->query("UPDATE `lp_module`
                        SET `name`= ?s
                        WHERE `module_id` = ?d
                        AND `course_id` = ?d", $_POST['newName'], $_POST['module_id'], $course_id);
            } else {
                $tool_content .= disp_message_box($langErrorNameAlreadyExists, "caution");
                $tool_content .= "<br />";
            }
        } else {
            $tool_content .= disp_message_box($langErrorEmptyName, "caution");
            $tool_content .= "<br />";
        }
        break;

    //display the form to modify the comment
    case "rqComment" :
        if (isset($_GET['module_id']) && is_numeric($_GET['module_id'])) {
            $module_id = intval($_GET['module_id']);
            //get current comment from DB
            $comment = Database::get()->querySingle("SELECT `comment`
                    FROM `lp_module`
                    WHERE `module_id` = ?d
                    AND `course_id` = ?d", $_GET['module_id'], $course_id);
            if ($comment && $comment->comment) {
                $tool_content .= "<form method='post' action='" . $_SERVER['SCRIPT_NAME'] . "?course=$course_code'>
                    <table width='99%' class='tbl'>
                    <tr><th class='left' colspan='2'>$langComments:</th></tr>
                    <tr><td colspan='2'>" . rich_text_editor('comment', 2, 60, $comment->comment) . "
                    <input type='hidden' name='cmd' value='exComment'>
                    <input type='hidden' name='module_id' value='$module_id'>
                    </td></tr>
                    <tr><td><input type='submit' value='$langImport'>
                    </td></tr></table>
                    </form><br />";
            } else {
                $tool_content .= "<form method='post' action='" . $_SERVER['SCRIPT_NAME'] . "?course=$course_code'>\n"
                        . '<table><tr><td valign="top">' . "\n"
                        . rich_text_editor('comment', 2, 30, '')
                        . "</td></tr></table>\n"
                        . "<input type='hidden' name='cmd' value='exComment'>\n"
                        . "<input type='hidden' name='module_id' value='$module_id'>\n"
                        . "<input type='submit' value='$langOk'>\n"
                        . "<br /><br />\n"
                        . "</form>\n";
            }
        } // else no module_id
        break;

    //make update to change the comment in the database for this module
    case "exComment":
        if (isset($_POST['comment']) && is_string($_POST['comment']) && isset($_POST['module_id']) && is_numeric($_POST['module_id'])) {
            Database::get()->query("UPDATE `lp_module`
                    SET `comment` = ?s
                    WHERE `module_id` = ?d
                    AND `course_id` = ?d", $_POST['comment'], $_POST['module_id'], $course_id);
        }
        break;
}


$sql = "SELECT M.*, count(M.`module_id`) AS timesUsed
        FROM `lp_module` AS M
   LEFT JOIN `lp_rel_learnPath_module` AS LPM ON LPM.`module_id` = M.`module_id`
        WHERE M.`contentType` != ?s
          AND M.`contentType` != ?s
          AND M.`contentType` != ?s
          AND M.`course_id` = ?d
        GROUP BY M.`module_id`
        ORDER BY M.`name` ASC, M.`contentType`ASC, M.`accessibility` ASC";

$result = Database::get()->queryArray($sql, CTSCORM_, CTSCORMASSET_, CTLABEL_, $course_id);
$atleastOne = false;

$num_results = count($result);

if (!$num_results == 0) {
    $tool_content .= "
        <table width=\"100%\" class=\"tbl_alt\">
        <tr>
        <th colspan=\"2\">" . $langLearningObjects . "</th>
        <th width=\"65\">" . $langTools . "</th>
        </tr>\n";
}
// Display modules of the pool of this course

$ind = 1;
foreach ($result as $list) {
    if ($ind % 2 == 0) {
        $style = 'class="odd"';
    } else {
        $style = 'class="even"';
    }

    //DELETE , RENAME, COMMENT

    $contentType_img = selectImage($list->contentType);
    $contentType_alt = selectAlt($list->contentType);
    $tool_content .= "
    <tr $style>
      <td align=\"left\" width=\"1%\" valign=\"top\"><img src=\"" . $themeimg . '/' . $contentType_img . "\" alt=\"" . $contentType_alt . "\" title=\"" . $contentType_alt . "\" /></td>
      <td align=\"left\"><b>" . q($list->name) . "</b>";

    if ($list->comment) {
        $tool_content .= "<br /><small style=\"color: #a19b99;\"><b>$langComments</b>: " . $list->comment . "</small>";
    }

    $tool_content .= "</td>
      <td><a href=\"" . $_SERVER['SCRIPT_NAME'] . "?course=$course_code&amp;cmd=eraseModule&amp;cmdid=" . $list->module_id . "\" onClick=\"return confirmation('" . clean_str_for_javascript($list->name . ': ' . $langUsedInLearningPaths . $list->timesUsed) . "');\"><img src=\"" . $themeimg . "/delete.png\" border=\"0\" alt=\"" . $langDelete . "\" title=\"" . $langDelete . "\" /></a>&nbsp;&nbsp;<a href=\"" . $_SERVER['SCRIPT_NAME'] . "?course=$course_code&amp;cmd=rqRename&amp;module_id=" . $list->module_id . "\"><img src=\"" . $themeimg . "/rename.png\" border=0 alt=\"$langRename\" title=\"$langRename\" /></a>&nbsp;&nbsp;<a href=\"" . $_SERVER['SCRIPT_NAME'] . "?course=$course_code&amp;cmd=rqComment&amp;module_id=" . $list->module_id . "\"><img src=\"" . $themeimg . "/comment_edit.png\" border=0 alt=\"$langComment\" title=\"$langComment\" /></a></td>\n";
    $tool_content .= "    </tr>";

    $atleastOne = true;
    $ind++;
} //end while another module to display

$tool_content .= "</table>";

if ($atleastOne == false) {
    $tool_content .= "<p class='alert1'>$langNoModule</p>";
}

draw($tool_content, 2, null, $head_content, $body_action);
