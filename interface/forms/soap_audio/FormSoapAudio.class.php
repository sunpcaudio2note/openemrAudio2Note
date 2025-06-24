<?php

/**
 * soap form
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2019 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Common\ORDataObject\ORDataObject;

define("EVENT_VEHICLE", 1);
define("EVENT_WORK_RELATED", 2);
define("EVENT_SLIP_FALL", 3);
define("EVENT_OTHER", 4);


/**
 * class FormHpTjePrimary
 *
 */
class FormSoapAudio extends ORDataObject
{
    /**
     *
     * @access public
     */


    /**
     *
     * static
     */
    var $id;
    var $date;
    var $pid;
    var $user;
    var $groupname;
    var $authorized;
    var $activity;
    var $subjective;
    var $objective;
    var $assessment;
    var $plan;
    var $transcript;
    var $remarks;

    /**
     * Constructor sets all Form attributes to their default value
     */

    function __construct($id = "", $_prefix = "")
    {
        if (is_numeric($id)) {
            $this->id = $id;
        } else {
            $id = "";
            $this->date = date("Y-m-d H:i:s");
        }

        $this->_table = "form_soap_audio";
        $this->activity = 1;
        $this->pid = $GLOBALS['pid'];
        if ($id != "") {
            $this->populate();
        }
    }
    function populate()
    {
        parent::populate();
    }

    function toString($html = false)
    {
        $string .= "\n"
            . "ID: " . $this->id . "\n";

        if ($html) {
            return nl2br($string);
        } else {
            return $string;
        }
    }
    function set_id($id)
    {
        if (!empty($id) && is_numeric($id)) {
            $this->id = $id;
        }
    }
    function get_id()
    {
        return $this->id;
    }
    function set_pid($pid)
    {
        if (!empty($pid) && is_numeric($pid)) {
            $this->pid = $pid;
        }
    }
    function get_pid()
    {
        return $this->pid;
    }
    function set_activity($tf)
    {
        if (!empty($tf) && is_numeric($tf)) {
            $this->activity = $tf;
        }
    }
    function get_activity()
    {
        return $this->activity;
    }

    function get_date()
    {
        return $this->date;
    }
    function set_date($dt)
    {
        if (!empty($dt)) {
            $this->date = $dt;
        }
    }
    function get_user()
    {
        return $this->user;
    }
    function set_user($u)
    {
        if (!empty($u)) {
            $this->user = $u;
        }
    }
    function get_subjective()
    {
        return $this->subjective;
    }
    function set_subjective($data)
    {
        if (!empty($data)) {
            $this->subjective = $data;
        }
    }
    function get_objective()
    {
        return $this->objective;
    }
    function set_objective($data)
    {
        if (!empty($data)) {
            $this->objective = $data;
        }
    }
    function get_assessment()
    {
        return $this->assessment;
    }
    function set_assessment($data)
    {
        if (!empty($data)) {
            $this->assessment = $data;
        }
    }
    function get_plan()
    {
        return $this->plan;
    }
    function set_plan($data)
    {
        if (!empty($data)) {
            $this->plan = $data;
        }
    }

    function get_transcript()
    {
        return $this->transcript;
    }

    function set_transcript($data)
    {
        if (!empty($data)) {
            $this->transcript = $data;
        }
    }

    function get_remarks()
    {
        return $this->remarks;
    }

    function set_remarks($data)
    {
        if (!empty($data)) {
            $this->remarks = $data;
        }
    }

    function persist()
    {
        parent::persist();
    }
}   // end of Form
