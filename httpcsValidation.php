<?php

/*
  Plugin Name: HTTPCS Validation
  Description: This plugin helps you to do the HTTPCS validation process automatically
  Version: 1.0.7
  Author: HTTPCS
  Author URI: https://www.httpcs.com/
  Text Domain: httpcsValidation
  Domain Path: /lang/
  License: GPL2
  
  HTTPCS Validation is free software: you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation, either version 2 of the License, or
  any later version.
 
  HTTPCS Validation is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  GNU General Public License for more details.
 
  You should have received a copy of the GNU General Public License
  along with HTTPCS Validation. If not, see https://www.gnu.org/licenses/gpl-2.0.html.
 */

class HttpcsValidation {

    private $url            = "";
    private $function       = "";
    private $company        = "";
    private $name           = "";
    private $phone          = "";
    private $email          = "";
    private $messageSuccess = "";
    private $messageError   = "";
    private $secondToken    = "";
    private $render         = "renderAdminDefault";
    private $fail           = 0;
    private $event          = "";
    private $locale         = "en";
    private $locale_user    = "en";
    private $fileName       = "";
    private $fileContent    = "";
    private $securedToken   = "";
    //Paths to HTTPCS
    private $PATHHTTPCS             = '';
    private $PATHCHECKURLEMAIL      = '';
    private $PATHCHECKEMAILPASSWORD = '';
    private $PATHCHECKFILE          = '';
    private $PATHCHECKFILEAGAIN     = '';
    private $PATHCONNECTION         = '/user/login';
    private $PATHFORGOTPASSWORD     = '/user/reset-password';
    
    public function __construct() {        
        if (!session_id()) {
            session_start();
        }
        if( !isset($_SESSION['securedToken']) || (isset($_SESSION['securedToken']) && empty($_SESSION['securedToken'])) ){
            $version = explode('.',PHP_VERSION);
            $PHP_VERSION_ID = ($version[0] * 10000 + $version[1] * 100 + $version[2]);
            if($PHP_VERSION_ID < 700000){
                $_SESSION['securedToken'] = hash('sha512', bin2hex(mt_getrandmax()));
            }else{
                $_SESSION['securedToken'] = hash('sha512', bin2hex(random_bytes(100)));
            }
        }
        $this->securedToken = $_SESSION['securedToken'];
    }
    
    public function init(){
        //Adding relative css and js files
        add_action('init', array($this, 'registerScript'));
        include 'httpcsFormHandler.php';
        include 'httpcsConfig.php';                

        $isValid = get_option('httpcsValidation');

        $objFormHandler = new HttpcsFormHandler();
        $objFormHandler->handleForm($_POST);

        if (is_object($objFormHandler) && $_SERVER['REQUEST_METHOD'] === "POST" && !$isValid && $_POST['securedToken'] == $this->securedToken) {
            //Creation
            if (isset($_POST['httpcs_crea']) && $_POST['httpcs_crea'] === 'Y') {
                $this->creation($objFormHandler);
            }
            //Connexion
            if (isset($_POST['httpcs_co']) && $_POST['httpcs_co'] === 'Y') {
                $this->connexion($objFormHandler);
            }
            //Retry
            if (isset($_POST['httpcs_retry']) && $_POST['httpcs_retry'] === 'Y') {
                $this->retry($objFormHandler);
            }
        }

        if ($isValid) {
            $this->render = "renderAdminValid";
        }

        //Appending plugin to menu
        add_action('admin_menu', array($this, 'addAdminMenu'));        
        //Loading translations
        add_action('plugins_loaded', array($this, 'loadTextdomain'));
    }

    /*
     * Handles creation
     */
    public function creation($objFormHandler) {
        $aFormHandler   = $objFormHandler->objToArray();
        $response[]     = $this->curlRequest($this->PATHCHECKURLEMAIL, $aFormHandler);
        $this->email    = $objFormHandler->getEmail();
        $this->name     = urldecode($objFormHandler->getName());
        $this->function = urldecode($objFormHandler->getFunction());
        $this->company  = urldecode($objFormHandler->getCompany());
        $this->phone    = urldecode($objFormHandler->getPhone());
        if($response[0] != 'error' && ( (isset($response[0]['response']) && $response[0]['response'] == '200') || $response[0]['response']['code'] == '200') ){
            if($this->handleResponse($response[0])){
                if (json_decode($response[0]['body'])->etat) {			
                    $uploads      = wp_upload_dir(null, false, false);
                    $relativePath = str_replace(get_site_url(), '', $uploads['baseurl']);
                    $response[]   = $this->curlRequest($this->PATHCHECKFILE, array('url' => $aFormHandler['url'], 'relativePath' => $relativePath . '/httpcs/', 'token' => json_decode($response[0]['body'])->token, 'email' => $this->email, 'event' => 'creation'));
                    if($response[1] != 'error' && ( (isset($response[1]['response']) && $response[1]['response'] == '200') || $response[1]['response']['code'] == '200') ){
                        if (!json_decode($response[1]['body'])->etat) {
                            $this->render      = "renderAdminRetry";
                            $this->secondToken = json_decode($response[1]['body'])->secondToken;
                            $this->event       = json_decode($response[1]['body'])->event;
                        } else {
                            $this->render = "renderAdminSuccess";
                            update_option('httpcsValidation', 1);
                        }
                    }else{
                        $response = array();
                        $response[0]['body'] = '{"etat":0,"code":8000}';
                    }
                }
            }else{
                $this->fileName = json_decode($response[0]['body'])->fileName;
                $this->fileContent = json_decode($response[0]['body'])->contentFile;
                $this->render = "renderWritingError";            
            }
        }else{
            $response = array();
            $response[0]['body'] = '{"etat":0,"code":8000}';
        }
        $this->renderResponses($response);
    }

    /*
     * Handles connexion
     */
    public function connexion($objFormHandler) {
        $aFormHandler   = $objFormHandler->objToArray();
        $uploads        = wp_upload_dir(null, false, false);
        $relativePath   = str_replace(get_site_url(), '', $uploads['baseurl']);
        $this->email    = $objFormHandler->getEmail();
        $this->password = urldecode($objFormHandler->getPassword());
        $response[]     = $this->curlRequest($this->PATHCHECKEMAILPASSWORD, array('url' => $aFormHandler['url'], 'relativePath' => $relativePath . '/httpcs/', 'password' => $aFormHandler['password'], 'email' => $this->email));
        if($response[0] != 'error' && ( (isset($response[0]['response']) && $response[0]['response'] == '200') || $response[0]['response']['code'] == '200') ){
            if (!json_decode($response[0]['body'])->etat) {
                $this->fail = 1;
            } else {
                if (isset(json_decode($response[0]['body'])->fileName)) {
                    if($this->handleResponse($response[0])){
                        //$uploads      = wp_upload_dir(null, false, false);
                        //$relativePath = str_replace(get_site_url(), '', $uploads['baseurl']);
                        $response[] = $this->curlRequest($this->PATHCHECKFILE, array('url' => $aFormHandler['url'], 'relativePath' => $relativePath . '/httpcs/', 'token' => json_decode($response[0]['body'])->token, 'email' => $this->email, 'event' => 'connexion'));
                        if($response[1] != 'error' && ( (isset($response[1]['response']) && $response[1]['response'] == '200') || $response[1]['response']['code'] == '200') ){
                            if (!json_decode($response[1]['body'])->etat) {
                                $this->render      = "renderAdminRetry";
                                $this->secondToken = json_decode($response[1]['body'])->secondToken;
                                $this->event       = json_decode($response[1]['body'])->event;
                            } else {
                                $this->render = "renderAdminSuccessCo";
                                update_option('httpcsValidation', 1);
                            }
                        }else{
                            $this->fail = 1;
                            $response = array();
                            $response[0]['body'] = '{"etat":0,"code":8000}';
                        }
                    }else{
                        $this->fileName = json_decode($response[0]['body'])->fileName;
                        $this->fileContent = json_decode($response[0]['body'])->contentFile;
                        $this->render = "renderWritingError";
                    }
                } else {
                    $this->render = "renderAdminSuccessCo";
                    update_option('httpcsValidation', 1);
                }
            }
        }else{
            $this->fail = 1;
            $response = array();
            $response[0]['body'] = '{"etat":0,"code":8000}';
        }
        $this->renderResponses($response);
    }

    /*
     * Handles retry
     */
    public function retry($objFormHandler) {
        $aFormHandler      = $objFormHandler->objToArray();
        $uploads           = wp_upload_dir(null, false, false);
        $relativePath      = str_replace(get_site_url(), '', $uploads['baseurl']);
        $this->email       = $objFormHandler->getEmail();
        $this->secondToken = urldecode($objFormHandler->getSecondToken());
        $this->event       = urldecode($objFormHandler->getEvent());
        $response[]        = $this->curlRequest($this->PATHCHECKFILEAGAIN, array('url' => $aFormHandler['url'], 'relativePath' => $relativePath . '/httpcs/', 'secondToken' => $this->secondToken, 'email' => $this->email, 'event' => $this->event));
        if($response[0] != 'error' && ( (isset($response[0]['response']) && $response[0]['response'] == '200') || $response[0]['response']['code'] == '200') ){
            if (!json_decode($response[0]['body'])->etat) {
                if(json_decode($response[0]['body'])->code == '9008'){
                    $this->render = "renderAdminDefault";
                }else{
                    $this->render = "renderAdminRetry";
                }
            } else {
                $this->render = "renderAdminSuccess";
                update_option('httpcsValidation', 1);
            }
        }else{
            $response = array();
            $response[0]['body'] = '{"etat":0,"code":8000}';
        }
        $this->renderResponses($response);
    }

    /*
     * Adds HTTPCS to the Wordpress'menu
     */
    public function addAdminMenu() {
        add_menu_page('HTTPCS Validation', 'HTTPCS', 'manage_options', 'httpcsvalidation', array($this, $this->render), plugin_dir_url(__FILE__) . 'img/httpcsIcon.png');
    }

    /*
     * Allows using custom css and js files
     */
    public function registerScript() {
        wp_register_style('httpcs-style', plugin_dir_url(__FILE__) . 'css/httpcs-css.css');
        wp_register_script('httpcs-script', plugin_dir_url(__FILE__) . 'js/httpcs-js.js');
        wp_enqueue_style('httpcs-style');
        wp_enqueue_script('httpcs-script');
        $wp_version = get_bloginfo('version');
        //Setting the language for translations
        if($wp_version >= 4.7 ){
            $this->locale_user = get_user_locale();
        }else{
            $this->locale_user = get_locale();
        }
        $locale = $this->locale_user;
        $tabIso = explode("_", $locale);
        if (!empty($tabIso)) {
            if ($tabIso[0] == 'fr') {
                $locale = "fr_FR";
                $this->locale = "fr";
                $this->PATHCONNECTION = '/utilisateur/connexion';
                $this->PATHFORGOTPASSWORD = '/utilisateur/reinitialiser-password';
            }
            if ($tabIso[0] == 'es') {
                $locale = "es_ES";
            }
        }
    }

    /*
     * Loads available translations
     */
    public function loadTextdomain() {
        load_plugin_textdomain('httpcsValidation', false, dirname(plugin_basename(__FILE__)) . '/lang/');
    }

    /*
     * Curl
     */
    public function curlRequest($urlHttpcs, $aFormHandler) {
        $response = wp_remote_post($urlHttpcs, array(
            'method'      => 'POST',
            'timeout'     => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
            'blocking'    => true,
            'headers'     => array(),
            'body'        => $aFormHandler,
            'cookies'     => array(),
            'user-agent'  => 'plugin-wordpress'
        ));
        if(is_wp_error($response)){
            return 'error';
        }
        return($response);
    }

    /*
     * Renders creation and the connexion form
     */
    public function renderAdminDefault() {
        $notices = "";
        if (!empty($this->messageSuccess)) {
            $notices = '<div class="notice updated is-dismissible"><p>';
            foreach ($this->messageSuccess as $code) {
                //$notices .= __($message, "httpcsValidation") . '<br>';
                $notices .= __($this->renderNotices($code), "httpcsValidation");
                $notices .= '<br>';
            }
            $notices .= '</p></div>';
        }
        if (!empty($this->messageError)) {
            $notices = '<div class="notice error is-dismissible"><p>';
            foreach ($this->messageError as $code) {
                //$notices .= __($message, "httpcsValidation") . '<br>';
                $notices .= __($this->renderNotices($code), "httpcsValidation");
                if ($code == '9002') {
                    $notices .= ', <a id="coClick">' . __('click here', "httpcsValidation") . '</a>';
                }
                if ($code == '9006') {
                    $notices .= ', <a id="creaClick">' . __('click here', "httpcsValidation") . '</a>';
                }
                $notices .= '<br>';
            }
            $notices .= '</p></div>';
        }

        if ($this->fail) {
            echo '<style>
            #creationContainer{
                display: none;
            }
            #connectionContainer{
                display: block;
            }
            </style>';
        }
        echo
        '<div id="pluginContainer">' .
        $notices
        . '<h1 id="logoHttpcsContainer"><img src="' . plugin_dir_url(__FILE__) . 'img/httpcsLogo.png" class="logoHttpcs"></h1>
            <div class="description">
                <div>' . __("This plugin allows you to validate your Wordpress website to HTTPCS.", "httpcsValidation") . '</div>
                <div>' . __("Please follow the instructions.", "httpcsValidation") . '</div>
            </div>
            
            <div id="creationContainer">
                <form method="post">
                    <input type="hidden" name="securedToken" value="'. $this->securedToken .'">
                    <input type="hidden" name="httpcs_crea" value="Y">
                    <h3 id="votreSite">' . __("YOUR WEBSITE", "httpcsValidation") . '</h3>
                    <div class="info">' . __("(all types of addresses, domains, URL, websites, extranets, SaaS...)", "httpcsValidation") . '</div>

                    <div>
                        <input type="url" name="url" required="required" class="form-control" placeholder="http://www.mon-site.com" value="' . get_site_url() . '" disabled>
                    </div>

                    <h4>' . __("CREATION OF YOUR HTTPCS ACCOUNT", "httpcsValidation") . '  </h4>
                    <div>
                        <input type="text" name="name" required="required" class="form-control" placeholder="' . __("Name and firstname", "httpcsValidation") . '" value="' . htmlentities($this->name) . '">
                    </div>
                    <div>
                        <input type="text" name="function" required="required" class="form-control" placeholder="' . __("Position", "httpcsValidation") . '" value="' . htmlentities($this->function) . '">
                    </div>
                    <div>
                        <input type="text" name="company" required="required" class="form-control" placeholder="' . __("Company", "httpcsValidation") . '" value="' . htmlentities($this->company) . '">
                    </div>
                    <div>
                        <input type="text" name="phone" required="required" class="form-control" placeholder="' . __("Phone", "httpcsValidation") . '" value="' . htmlentities($this->phone) . '">
                    </div>
                    <div>
                        <input type="email" name="email" required="required" class="form-control" placeholder="' . __("Your email address", "httpcsValidation") . '" value="' . htmlentities($this->email) . '">
                    </div>

                    <div>
                        <input type="submit" value="' . __("Access for free", "httpcsValidation") . '" class="btn btn-green"><br>
                        <div>' . __("Do you already have an HTTPCS account ?", "httpcsValidation") . ' <a onclick="showConnexion()">' . __("Click here", "httpcsValidation") . '</a></div>
                    </div>
                </form>
            </div>
            <div id="connectionContainer">
                <form method="post">
                    <input type="hidden" name="securedToken" value="'. $this->securedToken .'">
                    <input type="hidden" name="httpcs_co" value="Y">
                    <input type="hidden" name="url" value="' . get_site_url() . '">
                    <h3 id="votreSite">' . __("Connection", "httpcsValidation") . '</h3>
                    <div>
                        <input type="email" name="email" required="required" class="form-control" placeholder="' . __("Your email address", "httpcsValidation") . '" value="' . htmlentities($this->email) . '">
                    </div>
                    <div>
                        <input type="password" name="password" required="required" class="form-control" placeholder="' . __("Password", "httpcsValidation") . '">
                    </div>
                    <div>                        
                        <input type="submit" value="' . __("Log in", "httpcsValidation") . '" class="rf btn btn-primary">
                        <a target="_blank" href="'.$this->PATHHTTPCS.'/'.$this->locale.$this->PATHFORGOTPASSWORD.'" title="HTTPCS" class="rf forgotpw">' . __("Forgot your password?", "httpcsValidation") . '</a>
                        <div class="httpcsClearfix"></div>
                        <div>' . __("Don't have an HTTPCS account yet ?", "httpcsValidation") . ' <a onclick="showCreation()">' . __("Click here", "httpcsValidation") . '</a></div>
                    </div>
                </form>
            </div>
        </div>';
    }

    /*
     * Handles responses of the web service and creates file
     */
    public function handleResponse($aResponse = array()) {
        if (!empty($aResponse)) {
            $aResult = json_decode($aResponse['body']);
            $writingError = 1;
            if (isset($aResult->fileName) && isset($aResult->contentFile)) {
                $uploads = wp_upload_dir(null, false, false);
                $dirname = dirname($uploads['basedir'] . '/httpcs/' . $aResult->fileName);
                if (!is_dir($dirname)) {
                    if(!mkdir($dirname, 0755, true)){
                        $writingError = 0;
                    }
                }
                if($writingError){
                    if(is_writable($uploads['basedir'] . '/httpcs/')){
                        $handleFile = fopen($uploads['basedir'] . '/httpcs/' . $aResult->fileName, "w+");
                        if($handleFile){
                            fputs($handleFile, $aResult->contentFile);
                            fclose($handleFile);
                        }else{
                            $writingError = 0;
                        }
                    }else{
                        $writingError = 0;
                    }
                }
            }
            return $writingError;
        }
        return 0;
    }

    /*
     * Renders responses
     */
    public function renderResponses($aResponses) {
        if (!empty($aResponses)) {
            foreach ($aResponses as $aResponse) {
                if (!empty($aResponse)) {
                    $aResult = json_decode($aResponse['body']);

                    if ($aResult->etat) {
                        $messageSuccess[] = $aResult->code;
                    } else {
                        $messageError[] = $aResult->code;
                    }
                }
            }

            if (isset($messageSuccess)) {
                $this->messageSuccess = $messageSuccess;
            }
            if (isset($messageError)) {
                $this->messageError = $messageError;
            }
        }
    }

    /*
     * Renders retry view
     */
    public function renderAdminRetry() {
        $notices = "";
        if (!empty($this->messageSuccess)) {
            $notices = '<div class="notice updated is-dismissible"><p>';
            foreach ($this->messageSuccess as $code) {
                //$notices .= __($message, "httpcsValidation") . '<br>';
                $notices .= __($this->renderNotices($code), "httpcsValidation");
                $notices .= '<br>';
            }
            $notices .= '</p></div>';
        }
        if (!empty($this->messageError)) {
            $notices = '<div class="notice error is-dismissible"><p>';
            foreach ($this->messageError as $code) {
                //$notices .= __($message, "httpcsValidation") . '<br>';
                $notices .= __($this->renderNotices($code), "httpcsValidation");
                if ($code == '9002') {
                    $notices .= ', <a id="coClick">' . __('click here') . '</a>';
                }
                if ($code == '9006') {
                    $notices .= ', <a id="coClick">' . __('click here') . '</a>';
                }
                $notices .= '<br>';
            }
            $notices .= '</p></div>';
        }
        echo '<div id="pluginContainer">' .
        $notices
        . '<h1 id="logoHttpcsContainer"><img src="' . plugin_dir_url(__FILE__) . 'img/httpcsLogo.png" class="logoHttpcs"></h1>
            <div class="description">
                <div>' . __("Please try again by clicking on the button below.", "httpcsValidation") . '</div>
            </div>
            <div id="container">
                <form method="post">     
                    <input type="hidden" name="securedToken" value="'. $this->securedToken .'"> 
                    <input type="hidden" name="httpcs_retry" value="Y">
                    <input type="hidden" name="event" value="' . $this->event . '">
                    <input type="hidden" name="secondToken" value="' . $this->secondToken . '">
                    <input type="hidden" name="email" value="' . htmlentities($this->email) . '">
                    <button type="submit" class="btn btn-primary">' . __("Try again", "httpcsValidation") . '</button>
                </form>
            </div>
        </div>';
    }

    /*
     * Renders success view
     */
    public function renderAdminSuccess() {
        echo '<div id="pluginContainer">
        <h1 style="text-align:center;"><img src="' . plugin_dir_url(__FILE__) . 'img/httpcsLogo.png" class="logoHttpcs"></h1>
            <div id="container">
                ' . __("Account created and validated!", "httpcsValidation") . '<br>
                ' . __("Your password has been sent by email.", "httpcsValidation") . '
                <div><a target="_blank" href="'.$this->PATHHTTPCS.'/'.$this->locale.$this->PATHCONNECTION.'" title="HTTPCS" style="cursor: pointer;">' . __("Click here to access the HTTPCS console", "httpcsValidation") . '</a></div>
            </div>
        </div>';
    }
    
    /*
     * Renders successCo view
     */
    public function renderAdminSuccessCo() {
        echo '<div id="pluginContainer">
        <h1 id="logoHttpcsContainer"><img src="' . plugin_dir_url(__FILE__) . 'img/httpcsLogo.png" class="logoHttpcs"></h1>
            <div id="container">
                ' . __("Connection successful. Your website has been validated!", "httpcsValidation") . '
                <div><a target="_blank" href="'.$this->PATHHTTPCS.'/'.$this->locale.$this->PATHCONNECTION.'" title="HTTPCS" style="cursor: pointer;">' . __("Click here to access the HTTPCS console", "httpcsValidation") . '</a></div>
            </div>
        </div>';
    }

    /*
     * Renders valid view
     */
    public function renderAdminValid() {
        echo '<div id="pluginContainer">
        <h1 id="logoHttpcsContainer"><img src="' . plugin_dir_url(__FILE__) . 'img/httpcsLogo.png" class="logoHttpcs"></h1>
            <div id="container">
                ' . __("This website is already validated at HTTPCS!", "httpcsValidation") . '
                <div><a target="_blank" href="'.$this->PATHHTTPCS.'/'.$this->locale.$this->PATHCONNECTION.'" title="HTTPCS" style="cursor: pointer;">' . __("Click here to access the HTTPCS console", "httpcsValidation") . '</a></div>
            </div>
        </div>';
    }
    
    /*
     * Renders writing error view
     */
    public function renderWritingError() {
        $uploads = wp_upload_dir(null, false, false);
        $dirname = dirname($uploads['basedir'] . '/httpcs/' . $this->fileName);
        echo '<div id="pluginContainer">
        <h1 id="logoHttpcsContainer"><img src="' . plugin_dir_url(__FILE__) . 'img/httpcsLogo.png" class="logoHttpcs"></h1>
            <div id="container" style="text-align: left;word-break: break-word;">
                <div>' . __("Sorry, we were not able to make the directory and/or the required file to validate your website ! (You should check your rights)", "httpcsValidation") . '</div>
                <div>' . __("You should try to make them manually. Here is the file name and its content", "httpcsValidation") . ' :</div>
                <div><b>' . __("File name", "httpcsValidation") .' : </b>'. $this->fileName . '</div>
                <div><b>' . __("File content", "httpcsValidation") .' : </b>'. $this->fileContent . '</div>
                <div><b>' . __("You must place the file in this directory (perhaps you will need to make it)", "httpcsValidation") . ' :</b></div>
                <div>' . $dirname . '</div><br>
                <div>' . __("To try again", "httpcsValidation") .', <a onclick="window.location = window.location.href;" style="cursor: pointer;" class="underline">' . __("click here", "httpcsValidation") .'</a></div>                
            </div>
        </div>';
    }

    public function renderNotices($code) {
        include 'httpcsArrayLang.php';

        return $httpcsLang[$code];
    }

}

$HttpcsValidation = new HttpcsValidation();
$HttpcsValidation->init();
