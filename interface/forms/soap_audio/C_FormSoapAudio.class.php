<?php

/*
 * soap form
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2019 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2022 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once($GLOBALS['fileroot'] . "/library/forms.inc.php");
require_once("FormSoapAudio.class.php");

use OpenEMR\Common\Twig\TwigContainer;

class C_FormSoapAudio extends Controller
{
    private TwigContainer $twig;
    public function __construct()
    {
        $path = $this->getTemplatePath();
        $this->twig = new TwigContainer($path);
    }

    /**
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \Twig\Error\LoaderError
     */
    function default_action()
    {
        $form = new FormSoapAudio();
        return $this->twig->getTwig()->render(
            'soap_audio_form.twig',
            [
                "FORM_ACTION" => $GLOBALS['web_root'],
                "DONT_SAVE_LINK" => $GLOBALS['form_exit_url'],
                "data" => $form
            ]
        );
    }

    function view_action($form_id)
    {
        if (is_numeric($form_id)) {
            $form = new FormSoapAudio($form_id);
        } else {
            $form = new FormSoapAudio();
        }

        return $this->twig->getTwig()->render(
            'soap_audio_form.twig',
            [
                "FORM_ACTION" => $GLOBALS['web_root'],
                "DONT_SAVE_LINK" => $GLOBALS['form_exit_url'],
                "data" => $form
            ]
        );
    }

    function default_action_process()
    {
        if ($_POST['process'] != "true") {
            return;
        }

        $this->form = new FormSoapAudio($_POST['id']);
        parent::populate_object($this->form);

        $this->form->persist();
        if ($GLOBALS['encounter'] == "") {
            $GLOBALS['encounter'] = date("Ymd");
        }

        if (empty($_POST['id'])) {
            addForm(
                $GLOBALS['encounter'],
                "Audio2Note SOAP",
                $this->form->id,
                "soap_audio",
                $GLOBALS['pid'],
                $_SESSION['userauthorized']
            );
            $_POST['process'] = "";
        }
    }
    /**
     * @return string
     */
    private function getTemplatePath(): string
    {
        return \dirname(__DIR__) . DIRECTORY_SEPARATOR . "soap_audio/templates" . DIRECTORY_SEPARATOR;
    }
}
