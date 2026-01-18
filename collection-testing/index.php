<?php
    error_reporting(-1);
    function getUrlContent($url){
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        //echo $url;
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; .NET CLR 1.1.4322)');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        $data = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($httpcode>=200 && $httpcode<300) ? $data : false;
    }


    $xml = simplexml_load_file('collections.xml');
    
    $terms = array(
        'a',
        'an',
        'the',
        'emory',
        'acaemic',
        'admission',
        'research',
        'life',
        'news',
        'health',
        'heathcare',
        'art',
        'science',
        'degree',
        'pdf',
        'document'
    
    );
    
    $q = implode('+OR+', $terms);
    $p_cnt = count($xml->collection); 
    for($i = 0; $i < $p_cnt; $i++) { 
        $collection = $xml->collection[$i]; 
      
        $collectionName = $collection['Name']->__toString();
      
        $searchGSAURL = 'http://search.emory.edu/search?'
                        .'q='
                        .$q 
                        .'&site='
                        .$collectionName;
      
        $searchMindBreezeURL = 'https://mindbreeze.emory.edu/plugin/GSA/search?'
                        .'output=xml'
                        .'&q='
                        .$q 
                        .'&requiredfields=collection:' 
                        .$collectionName;
      
        echo '<h1>'.$collectionName.'</h1>';
      
        echo '<h2>URLS</h2>';
      
        echo '<p>GSA: '. $searchGSAURL . '</p>';
        echo '<p>Mindbreeze: '. $searchMindBreezeURL . '</p>';
      
        $GSAResults = getUrlContent($searchGSAURL);
      
        $MindBreezeResults = getUrlContent($searchMindBreezeURL);
        
        echo '<h2>Results</h2>';
        if(!$GSAResults) {
            echo '<p style="color:red">GSA: ERROR IN GETTING RESULTS</p>';
            $GSAcount = 0;
        } else {
            $GSAxml = new SimpleXMLElement($GSAResults);
            if($GSAxml->xpath('/GSP/RES/M')){
                $GSAcount = intval($GSAxml->RES->M);
            } else {
              echo '<p style="color:red">GSA: No results</p>';
              $GSAcount = 0;
            }
        }
      
        if(!$MindBreezeResults) {
            echo '<p style="color:red">Mindbreeze: ERROR IN GETTING RESULTS</p>';
            $MindBreezecount = 0;
        } else {
            $MindBreezexml = new SimpleXMLElement($MindBreezeResults);
            if($MindBreezexml->xpath('/GSP/RES/M')){
                $MindBreezecount = intval($MindBreezexml->RES->M);//           
            } else {
                echo '<p style="color:red">Mindbreeze: No results</p>';
                $MindBreezecount = 0;
            }
        }
      
        echo  '<p>Counts' 
                .'<br /> GSA: '
                . $GSAcount .
                '<br /> Mindbreeze:'
                . $MindBreezecount .
                "</p>";
                
        if ($GSAcount > 0 && $MindBreezecount > 0 && $GSAcount/$MindBreezecount > 0.9 && $GSAcount/$MindBreezecount < 1.1) {
            echo '<p style="color:green"> Collection looks ok by count </p>';
        } else {
            echo '<p style="color:red"> Collection does not looks ok by count </p>';
        }
    }
?>