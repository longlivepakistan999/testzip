<?php
    
    error_reporting(E_ALL);
    ini_set('display_errors', True);
    
    include $_SERVER['DOCUMENT_ROOT'] .'/includes/util.php';
    
    
    if( !isset($_GET["as_q"]) 
        && !isset($_GET["q"]) 
        && !isset($_GET["requiredfields"]) 
    ) {
        include $_SERVER['DOCUMENT_ROOT'] . '/index.html';
        exit;
    }
    
    
    $q = '';
    
    if(isset($_GET["searchString"])&& $_GET["searchString"] != ''){
        $q = str_replace('%20', '+', $_GET['searchString']);
    }
    
    if(isset($_GET["as_q"])&& $_GET["as_q"] != ''){
        $q = str_replace('%20', '+', $_GET['as_q']);
    }
    
    if(isset($_GET["q"]) && $_GET["q"] != ''){
        $q = str_replace('%20', '+', $_GET['q']);
    }
    
    
    
    if(isset($_GET["site"])){
        $site = $_GET['site'];
    } else {
        $site = 'default_collection';
    }
    
    
    
    $searchURL = 'https://search-test.emory.edu/plugin/GSA/search?output=xml';
    $bestBetsURL = 'https://search-test.emory.edu/plugin/GSA/search?output=xml&requiredfields=category:BestBets&q=%20';
    
    //Add GSA parameters if there
    if(isset($_GET["start"])){
        $searchURL = $searchURL . '&start=' . $_GET["start"];
    }
    
    if(isset($_GET["num"])){
        $searchURL = $searchURL . '&num=' . $_GET["num"];
    }
    
    // if(isset($_GET["getfields"])){
    //     if($_GET["getfields"] == '*'){
    //         $searchURL = $searchURL . '&getfields=' . 
    //         'viewport.content_type.featured_subject.subject.resource_type' .
    //         'letter.feature_in.include_in.full_title.external_link' .
    //         'location.fulltext.alert_type.full_description.description' .
    //         'coverage.alert.whsc_full_description.status.keywords';
    //     } else {
    //         $searchURL = $searchURL . '&getfields=' . $_GET["getfields"];
    //     }
    // }
    
    if(isset($_GET["requiredfields"])){
        $searchURL = $searchURL . '&requiredfields=' . str_replace('|', '%7C', str_replace('%7C', '%257C', str_replace(' ', '+', $_GET['requiredfields'])));
        if($site != 'default_collection'){
            $searchURL = $searchURL . ".collection:" . $site;
        }
    } else {
        if($site != 'default_collection'){
            $searchURL = $searchURL . '&requiredfields=collection:' . $site;
        }
    }
    
    if(isset($_GET["filter"])){
        $searchURL = $searchURL . '&filter=' . $_GET["filter"];
    }
    if(isset($_GET["paging"])){
        $searchURL = $searchURL . '&paging=' . $_GET["paging"];
    }
    
    if(isset($_GET["proxystylesheet"])){
        $searchURL = $searchURL . '&proxystylesheet=' . $_GET["proxystylesheet"];
    }

    //Add the query
    if($q != ''){
        $searchURL = $searchURL . '&q=' . str_replace('%20', "+", urlencode (urldecode($q)));
        $bestBetsURL = $bestBetsURL . str_replace('%20', "+",urlencode (urldecode($q)));
        
    }
    
    header("x_q:" . urldecode($q));
    header("x_query:" . $searchURL);
    header("x_query_keymatch:" . $bestBetsURL);
    
    $strSearchResults = getUrlContent($searchURL);
    $strBestBetsResults = getUrlContent($bestBetsURL);
    
    if(isset($_GET["proxystylesheet"])){
        $proxystylesheet = $_GET['proxystylesheet'];
    } else {
        $proxystylesheet = '';
    }
    
    $xml = new SimpleXMLElement($strSearchResults);
    $bestBets = new SimpleXMLElement($strBestBetsResults);
    foreach ($bestBets->xpath('/GSP/RES/R') as $keyMatchResult) {
        $GMNode = $xml->addChild('GM');
        $GMNode->addChild('GD', $keyMatchResult->MT['V']->__toString());
        $GLnode = $GMNode->addChild('GL');
        $GLdom = dom_import_simplexml($GLnode);
        $cdata = $GLdom->ownerDocument->createCDATASection($keyMatchResult->U->__toString());
        $GLdom->appendChild($cdata);
    }
        
    
    if($proxystylesheet == '') {
         header("Content-type: text/xml");
          header("Access-Control-Allow-Origin: *");
         echo $xml->asXML();
    } else {
        $xsltDoc = new DOMDocument();
        //Change base on frontend
        if ($proxystylesheet == 'default_collection') {
        //$xsltDoc->load("xslt/default_frontend_stylesheet.en.xslt");
        $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/homepage_stylesheet.en.xslt");
        } elseif ($proxystylesheet == 'emoryhealthcare') {
            $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/emoryhealthcare_stylesheet.en.xslt");
        } elseif ($proxystylesheet == 'oxford') {
            $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/oxford_stylesheet.en.xslt");
        } elseif ($proxystylesheet == 'IT' || $proxystylesheet == 'it_frontend' ) {
            $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/it_frontend_stylesheet.en.xslt");
        } elseif ($proxystylesheet ==  'yerkes') {
            // $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/yerkes_stylesheet.en.xslt");
        
            $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/homepage_stylesheet.en.xslt");
        } elseif ($proxystylesheet == 'homepage') {
            $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/homepage_stylesheet.en.xslt");
        } elseif ($proxystylesheet == 'homepage_noresults') {
            $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/homepage_noresults_stylesheet.en.xslt");
        } elseif ($proxystylesheet == 'news_center' 
            && isset($_GET['proxystylesheet'])
            && $_GET['proxystylesheet'] == 'news_center_tags'
            ) {
            $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/news_center_tags_stylesheet.en.xslt");
        } elseif ($proxystylesheet == 'news_center') {
            $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/news_center_stylesheet.en.xslt");
        
        } elseif ($proxystylesheet == 'medicine') {
            $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/med-search.xslt");
        } elseif ($proxystylesheet == 'inside-medicine') {
            $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/inside-med-search.xslt");
        
        
        }elseif ($proxystylesheet == 'oxford_new') {
            $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/oxford_new_stylesheet.en.xslt");
        } elseif ($proxystylesheet ==  'policies' || $proxystylesheet ==  'policies_frontend') {
            $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/policies_frontend_stylesheet.en.xslt"); 
        } elseif ($proxystylesheet ==  'xml') {
            $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/echo.xsl");
        } elseif ($proxystylesheet ==  'standard'){ 
            include $_SERVER['DOCUMENT_ROOT'] . '/front-ends/standard/header.php';
            include $_SERVER['DOCUMENT_ROOT'] . '/front-ends/standard/results.php';
            include $_SERVER['DOCUMENT_ROOT'] . '/front-ends/standard/footer.php';
            exit;
        } else {
            //$xsltDoc->load("xslt/default_frontend_stylesheet.en.xslt");
            if($q == ''){
                $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/homepage_stylesheet.en.xslt");
            }
            $xsltDoc->load($_SERVER['DOCUMENT_ROOT'] ."/xslt/echo.xsl");
        }
        $xslt = new XSLTProcessor();
        $xslt->importStylesheet($xsltDoc);
        if($proxystylesheet ==  'xml' || $proxystylesheet == '') {
            header("Content-type: text/xml");
             header("Access-Control-Allow-Origin: *");
        } else {
            header("Content-type: text/html");
        }
        echo $xslt->transformToXml($xml);
    }
    
?>
