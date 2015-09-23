<?php

// This file should be called from a very simple email-address-only form in your
// ebay listing or store page, like this:
//    <form target="_blank" action="https://your-domain.com/path/to/this/hostedHubspotForm.php" method="post">
//        <input type="email" name="email" placeholder="Your Email Here">
//        <button type="submit" value="Subscribe">Subscribe</button>
//    </form>

    $hubspotRenderer = new hubspotEbaySignup();
    
    // Determine whether this is a raw submission or a Name update
    if(isset($_REQUEST['status']) && $_REQUEST['status'] == 'nameUpdate') {
        // We got the email address, now lets see if they will give us their name as well.
        
        $returnUrl = $hubspotRenderer->getReturnUrl($_REQUEST['pageUrl']);
        if($_REQUEST['ipAddress'] == $_SERVER['REMOTE_ADDR']){
            if(isset($_REQUEST['email'])) {
                $content = $hubspotRenderer->updateContactInfo($_REQUEST);
            } else {
                $content = '<h1>Unfortunately...</h1>' . PHP_EOL .
                    '<p>an error occured.</p>' .  PHP_EOL .
                    '<p><a class="btn btn-default" href="' . $returnUrl . '">Take me back to eBay, thanks...</a></p>';
            }
        } else {
            $content = '<h1>Unfortunately...</h1>' . PHP_EOL .
                '<p>there is an ip address mismatch.</p>' .  PHP_EOL .
                '<p><a class="btn btn-default" href="' . $returnUrl . '">Take me back to eBay, thanks...</a></p>';
        }
        $hubspotRenderer->renderPage($content);
    } else {
        // Fill out the Hubspot form with just the email address
        $hsForm['hubspotutk']     = $_COOKIE['hubspotutk']; //grab the cookie from the visitors browser.
        $hsForm['ipAddress']      = $_SERVER['REMOTE_ADDR']; //IP address too.
        $hsForm['pageUrl']        = $_SERVER['HTTP_REFERER'];
        $hsForm['pageName']       = 'Unknown';
        $hsForm['email']          = $_REQUEST['email'];
        $returnUrl = $hubspotRenderer->getReturnUrl($_SERVER['HTTP_REFERER']);
        $result = $hubspotRenderer->submitHubspotForm($hsForm);
        $emailText = 'Email';
        if($result['err' != 'none']) {
            $emailText = 'Verify Email';
        }
$content = <<<HTML
    <form class="form-mini" method="post" action="https://your-domain.com/path/to/this/hostedHubspotForm.php">

        <div class="form-title-row">
            <h1>{Your Company Name} Newsletter</h1>
        </div>

        <div class="form-row">
            <label>
                <span>First Name</span>
                <input type="text" name="firstName">
            </label>
        </div>

        <div class="form-row">
            <label>
                <span>Last Name</span>
                <input type="text" name="lastName">
            </label>
        </div>

        <div class="form-row">
            <label>
                <span>$emailText</span>
                <input type="email" name="email" value="{$_REQUEST['email']}">
            </label>
        </div>

        <div class="form-row">
            <input type="hidden" name="status" value="nameUpdate">
            <input type="hidden" name="hubspotutk" value="{$_COOKIE['hubspotutk']}">
            <input type="hidden" name="ipAddress" value="{$_SERVER['REMOTE_ADDR']}">
            <input type="hidden" name="pageUrl" value="$returnUrl">
            <input type="hidden" name="pageName" value="{$hubspotRenderer->pageName}">

            <button type="submit">Subscribe</button>
        </div>

    </form>
    <p><a class="btn btn-default" href="$returnUrl">Take me back to eBay, thanks...</a></p>
HTML;
$hubspotRenderer->renderPage($content);
    }

class hubspotEbaySignup {

    protected $portalId  = '{your-hubspot-user-id}';
    protected $formGUID  = '{hubspot guid for the "form" you will be submitting to}';
    protected $apiKey    = '{your hubspot api key}';

    protected $ebayAppId = '{your-ebay-app-id-here}';

    public $pageName;
    public $returnPageUrl;
    public function __construct(){}

    function submitHubspotForm($hsForm = array()) {
        $pageName    = $hsForm['pageName'];
        $pageUrl     = $hsForm['pageUrl'];
        $hubspotutk  = $hsForm['hubspotutk'];
        $ipAddress   = $hsForm['ipAddress'];
        $email       = $hsForm['email'];

        $ebayItemId = $this->getEbayId($pageUrl);
        if($ebayItemId) {
            $ebayPageTitle = $this->getEbayPageTitle($ebayItemId);
        }

    // create hubspot "context" object
        $hs_context      = array(
            'hutk' => $hubspotutk,
            'ipAddress' => $ipAddress,
            'pageUrl' => $pageUrl,
            'pageName' => (!empty($ebayPageTitle)?$ebayPageTitle:$pageName),
        );

        $hs_context_json = json_encode($hs_context);

    // Populate post string with values from the ebay form, if you uncomment lines
    // here, you have to add form inputs in your ebay form.
        $postString =
        //     "firstname=" . urlencode($firstname) . "&" . 
        //     "lastname=" . urlencode($lastname) . "&" . 
        "email=" . urlencode($email) . "&" .
        //     "phone=" . urlencode($phonenumber) . "&" . 
        //     "company=" . urlencode($company) . "&" .
        "hs_context=" . urlencode($hs_context_json);

    // Create the Hubspot Api Url
        $addContactViaFormUrl = 'https://forms.hubspot.com/uploads/form/v2/' . $this->portalId . '/' . $this->formGUID;

    // Submit Hubspot Form Api Request
        $result = $this->requestPage($addContactViaFormUrl, $postString);
        return $result;

    }

    function getEbayId($pageUrl){

    // try to identify the ebay item number and get the page title
        // extract ebay item number from url if possible
        if(strpos($pageUrl, 'itm=')) {
            $positionString = 'itm=';
        }
        if(strpos($pageUrl, 'item=')) {
            $positionString = 'item=';
        }
        preg_match('/' . $positionString . '([^&]*)&/',$pageUrl,$itemNumberMatchSet);

        // request item info and extract the page title for hubspot 
        if(isset($itemNumberMatchSet[1])){
            $ebayId = $itemNumberMatchSet[1];
            $this->returnPageUrl = "http://www.ebay.com/itm/" . $ebayId;
            return $ebayId;
        }
        return false;
    }

    function getEbayPageTitle($ebayId){
        $ebayShoppingApiUrl = 'http://open.api.ebay.com/shopping?' .
            'callname=GetSingleItem&' .
            'responseencoding=JSON&' .
            'appid=' . $this->ebayAppId . '&' .
            'version=933&' .
            'siteid=0&' .
            'ItemID=' . $ebayId;
        $ebayPage = $this->requestPage($ebayShoppingApiUrl, '');
        if(isset($ebayPage['response']['Item']['Title'])) {
            $ebayPageName = $ebayPage['response']['Item']['Title'];
            $this->pageName = $ebayPageName;
            return $ebayPageName;
        }
        return false;
    }

    function getReturnUrl($pageUrl) {
        if(!empty($this->returnPageUrl)){
            $url = $this->returnPageUrl;
        } else {
            $ebayItemId = $this->getEbayId($pageUrl);
            if($ebayItemId) {
                $this->returnPageUrl = "http://www.ebay.com/itm/" . $ebayItemId;
                $url = $this->returnPageUrl;
            } else {
                $url = $pageUrl;
            }
        }
        str_replace(array('&', '?'), array('%26', '%3F'), $url);
        return $url;
    }


    function updateContactInfo($requestData){

        $returnUrl = $this->getReturnUrl($requestData['pageUrl']);

        $getContactByEmailUrl = 'https://api.hubapi.com/contacts/v1/contact/email/' . $requestData['email'] . '/profile?hapikey=' . $this->apiKey;
        $result = $this->requestPage($getContactByEmailUrl, '');
        if($result['err'] != 'none'){
            // todo: no email address in hubspot.... we should ask again for the email here
            $content = '<h1>Perry Null Trading Newsletter</h1><p>Success!</p><p><a class="btn btn-default" href="' . $returnUrl . '">Thank you for signing up! Click to return to eBay...</a></p>';
            return $content;
        } else {
            $contactId = $result['response']['identity-profiles']['vid'];
            $contactUpdateUrl = 'https://api.hubapi.com/contacts/v1/contact/vid/' . $contactId . '/profile?hapikey=' . $this->apiKey;
            $time = time();
            if(isset($requestData['firstName'])){
                $postString['properties'][] = array(
                        "property" => "firstname",
                        "value" => $requestData['firstName'],
                        "timestamp" => $time,
                    );
            }
            if(isset($requestData['lastName'])) {
                $postString['properties'][] = array(
                        "property" => "lastname",
                        "value" => $requestData['lastName'],
                        "timestamp" => $time,
                    );
            }
            if(count($postString['properties']) > 0) {
                $result = $this->requestPage($contactUpdateUrl, urlencode(json_encode($postString)));
                if($result['err'] != 'none') {
                    $content = '<h1>Unfortunately...</h1>' . PHP_EOL .
                        '<p>' . $result['err'] . '</p>' .  PHP_EOL .
                        '<p><a class="btn btn-default" href="' . $returnUrl . '">Take me back to eBay, thanks...</a></p>';
                    return $content;
                } else {
                    $content =  '<h1>Success!</h1>' . PHP_EOL .
                        '<p>' . $requestData['firstName'] . ', thank you for signing up! <a class="btn btn-default" href="' . $returnUrl . '">Click to return to eBay...</a></p>';
                    return $content;
                }
            }
            $content = '<h1>Okay!</h1>' . PHP_EOL .
                '<p>You are ready to recive our newsletter!</p>' .  PHP_EOL .
                '<p><a class="btn btn-default" href="' . $returnUrl . '">Take me back to eBay, thanks...</a></p>';
            return $content;
        }
    }

    function renderPage($content){
    $page = <<<HTML
    <!DOCTYPE html>
    <html>

    <head>

        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{YOUR COMPANY NAME} Newsletter</title>

        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap-theme.min.css">

        <link rel="stylesheet" href="css/styles.css">

    </head>


        <div class="main-content">
            <div class="form-mini-container">
$content
            </div>
        </div>

    </body>
    </html>

HTML;
        echo $page;
    }


    function requestPage($url, $postString = ''){
        $ch = @curl_init();
        if($postString != '') {
            @curl_setopt($ch, CURLOPT_POST, true);
            @curl_setopt($ch, CURLOPT_POSTFIELDS, $postString);
        }
        @curl_setopt($ch, CURLOPT_URL, $url);
        @curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded'
        ));
        @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response    = @curl_exec($ch);
        $status_code = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);

        switch($status_code){
            case "404":
            case "500":
                $err = "We cannot process the request at the moment, please call {YOUR PHONE #} if you need help.";
                break;
            case "204":
            case "302":
                $err = false;
                break;
        }
        return array('err' => $err, 'response' => json_decode($response, true));
    }

}

?>
