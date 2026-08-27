<?php
$urls = file('feeds.txt', FILE_IGNORE_NEW_LINES);
$postLimit = 1000;
$result = '';
$feeda = array();
$feedn = 0;
foreach ($urls as $url) {
echo "\n"."Getting feed from: ". $url." \n";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/102.0.0.0 Safari/537.36',
        CURLOPT_REFERER => 'https://www.bing.com/',
        CURLOPT_TIMEOUT_MS => 7000,
        CURLOPT_ENCODING => 'gzip',
        CURLOPT_FOLLOWLOCATION => true
    ]);
    
    $response = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch) || $httpcode != 200) {
         echo "\n"."Error loading feed from " . $url . " " . $httpcode . " " . curl_error($ch) ." \n ";
         $feedn++;
        continue;
    }
    curl_close($ch);


    $response = str_ireplace(array("media:thumbnail",'<media:group>','</media:group>'), array("thumbnail",'',''), $response);
    $feed = simplexml_load_string($response);
    
    if ($feed && isset($feed->channel->item[0]->link)) {
        // Get first <item><link>
        $firstLink = (string)$feed->channel->item[0]->link;
        echo "\n"."Link assessed: ". $firstLink." \n";
    
        // Extract podcast name from URL, e.g., last path segment after "/pod/show/"
        if (preg_match('#/pod/show/([^/]+)/#', $firstLink, $matches)) {
            $podcastName = $matches[1];   // e.g., "desi-academic-podcast"
            echo "\n"."Podcast name found: ". $podcastName." \n";
        } else {
            $podcastName = 'podcast';
        }
    
        // Sanitize for filename
        $safeName = preg_replace('/[^a-zA-Z0-9_-]/', '_', strtolower($podcastName));
        echo "\n"."Final file name: ". $safeName." \n";
    
        // Full filesystem path for PNG
        $podcastLogoPath = __DIR__ . '/' . $safeName . '.png';
        
        // --- Fetch podcast logo from <itunes:image> ---
        $podcastLogoUrl = null;
        $itunes_ns = 'http://www.itunes.com/dtds/podcast-1.0.dtd';
        $itunesImage = $feed->channel->children($itunes_ns)->image;
        if ($itunesImage && isset($itunesImage->attributes()->href)) {
            $podcastLogoUrl = (string)$itunesImage->attributes()->href;
        }
        
        if ($podcastLogoUrl) {
            echo "Fetching podcast logo from: $podcastLogoUrl\n";
        
            // Use file_get_contents or cURL if allow_url_fopen disabled
            $logoData = @file_get_contents($podcastLogoUrl);
            if ($logoData) {
                $result = @file_put_contents($podcastLogoPath, $logoData);
                if ($result) {
                    echo "Podcast logo saved to: $podcastLogoPath\n";
                } else {
                    echo " Error: Failed to save logo to $podcastLogoPath\n";
                }
            } else {
                echo "Error: Failed to fetch logo data from $podcastLogoUrl\n";
            }
        } else {
            echo "Error: <itunes:image> not found in RSS feed.\n";
        }

        // --- Episode loop ---
        $feedType = strtolower($feed->getName());
        $count = 0;
        if ($feedType === 'rss') {
            foreach ($feed->channel->item as $item) {
                if ($count >= $postLimit) {
                    break;
                }
                $description = $item->description;
                $image = null;
               if (isset($item->image) && isset($item->image->url)) {
                    $image = $item->image->url;
                         
                } else if (isset($item->thumbnail) && isset($item->thumbnail->attributes()['url'])) {
                    $image = (string)$item->thumbnail->attributes()['url'];
         
                } else {   
                    preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $description, $imagematch);
                    if ($imagematch && isset($imagematch['src'])) {
                        $image = $imagematch['src'];
                    } else if (isset($feed->channel->image) && isset($feed->channel->image->url)) {
                        $image = $feed->channel->image->url;
                    }
                      
                }
                                 
                $audiourl = null;
                if (isset($item->enclosure) && isset($item->enclosure['url']) && isset($item->enclosure['type']) && strpos($item->enclosure['type'], 'audio/mpeg') !== false) {
                 $audiourl = $item->enclosure['url'];
                }

                $feeda[$feedn]['link'] = (string) $item->link;
                $feeda[$feedn]['title'] = (string) $item->title;
                if(empty($feeda[$feedn]['title'])) {$feeda[$feedn]['title'] = substr(strip_tags($description),0,140);}
                $feeda[$feedn]['ch'] = (string) $feed->channel->title;
                $feeda[$feedn]['date'] = strtotime((string) $item->pubDate);
                  
                $feeda[$feedn]['image'] = (string) $image;
                $feeda[$feedn]['audio'] = (string) $audiourl;
                $count++; $feedn++;
            }
        } elseif ($feedType === 'feed') {
            foreach ($feed->entry as $entry) {
                if ($count >= $postLimit) {
                    break;
                }
                $content = $entry->content;
                $image = null;
                if (isset($entry->image) && isset($entry->image->url)) {
                 $image = $entry->image->url;
                } elseif (isset($entry->{'thumbnail'}) && isset($entry->{'thumbnail'}->attributes()['url'])) {
                $image = (string) $entry->{'thumbnail'}->attributes()['url'];
                } else {
                 $content = $entry->content;
                 preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $content, $imagematch);
                 $image = ($imagematch && isset($imagematch['src'])) ? $imagematch['src'] : (isset($feed->image) && isset($feed->image->url) ? $feed->image->url : null);
                 }
                $audiourl = null;
                if (isset($entry->link) && isset($entry->link['rel']) && $entry->link['rel'] == 'enclosure' && isset($entry->link['href']) && isset($entry->link['type']) && strpos($entry->link['type'], 'audio/mpeg') !== false) {
                    $audiourl = $entry->link['href'];
                }

                $feeda[$feedn]['link'] = (string) $entry->link['href'];
                $feeda[$feedn]['title'] = (string) $entry->title;
                $feeda[$feedn]['ch'] = (string) $feed->title;
                $feeda[$feedn]['date'] = strtotime((string) ($entry->published ?? $entry->updated));
                $feeda[$feedn]['image'] = (string) $image;
                $feeda[$feedn]['audio'] = (string) $audiourl;
            

                $count++; $feedn++;
            }
        }
     
     
    } else {
         echo 'Failed to parse feed from ' . $url;
         $feedn++;
    }
}
usort($feeda, fn($a, $b) => $b['date'] <=> $a['date']);
$outhtml = '';
$outoptions = '<option value="All">All Channels</option>';
$outchannels = array();
$index = 0;

// Generate Tweets HTML content from tweets.json
$tweetshtml = '';
$tweetCount = 0;
$tweetsJsonPath = __DIR__ . '/tweets.json';
if (file_exists($tweetsJsonPath)) {
    $tweetsData = json_decode(file_get_contents($tweetsJsonPath), true);
    if ($tweetsData && isset($tweetsData['data'])) {
        foreach ($tweetsData['data'] as $tweet) {
            // Extract tweet ID from URL: https://twitter.com/username/status/TWEET_ID
            $tweetUrl = isset($tweet['url']) ? $tweet['url'] : '';
            preg_match('/status\/(\d+)/', $tweetUrl, $matches);
            $tweetId = $matches[1] ?? '';
            
            if ($tweetId) {
                $tweetshtml .= '<blockquote class="twitter-tweet" data-theme="dark"><p></p><a href="' . htmlspecialchars($tweetUrl) . '"></a></blockquote>' . "\n";
                $tweetCount++;
            }
        }
    }
}

// Note: Twitter script is now in tweets.html template with lazy loading

foreach($feeda as $post) {
if(!in_array($post['ch'],$outchannels)) {
$outoptions .= '<option value="'.$post['ch'].'">'.$post['ch'].'</option>';
$outchannels[] = $post['ch'];
}
$isaudio = !empty($post['audio']) ? 1 : 0;
$outhtml .= '<div class="post" data-channel="'.$post['ch'].'" data-ts="'.$post['date'].'" data-audio="'.$isaudio.'">';
if(!empty($post['image'])){
$outhtml .= '<div class="leftpan"><img src="'.$post['image'].'" alt="'.$post['title'].'"/ ></div>';
}
else {
  $domain = parse_url($post['link'], PHP_URL_HOST);
  $outhtml .= '<div class="leftpan"><img src="https://s2.googleusercontent.com/s2/favicons?domain='.urlencode($domain).'" alt="'.$post['title'].'"/><span class="domain">'.$domain.'</span></div>';
}
$outhtml .= '<div class="rightpan"><div class="feedname"><span class="channel">'.$post['ch'].'</span> &bull; <span class="date">'.date('M d, Y',$post['date']).'</span></div>
<h2><a href="'.$post['link'].'" target="_blank">'.$post['title'].'</a></h2>';
if(!empty($post['audio'])){
$outhtml .= '<div class="audio">
<button data-aid="'.$index.'">Play</button>
<audio src="'.$post['audio'].'" preload="metadata" aid="'.$index.'"  controls></audio></div>';
$index++;
}





$outhtml .='
</div></div>
';}
file_put_contents('feed.json', json_encode($feeda));

// ===== Parse About.md =====
$aboutHtml = '';
if (file_exists('about.md')) {
    $aboutMarkdown = file_get_contents('about.md');
    // Simple markdown to HTML conversion
    $aboutHtml = parseMarkdown($aboutMarkdown);
}

// ===== Parse Recommendations CSV =====
$recommendationsHtml = '';
if (file_exists('dap_recommendations.csv')) {
    $csv_file = fopen('dap_recommendations.csv', 'r');
    $headers = fgetcsv($csv_file);
    
    $recommendationsHtml = '<table id="recommendations-table"><thead><tr>';
    foreach ($headers as $header) {
        $recommendationsHtml .= '<th>' . htmlspecialchars($header) . '</th>';
    }
    $recommendationsHtml .= '</tr></thead><tbody>';
    
    while (($row = fgetcsv($csv_file)) !== FALSE) {
        $recommendationsHtml .= '<tr>';
        foreach ($row as $i => $cell) {
            $cell = trim($cell);
            // Remove surrounding quotes if present
            if (substr($cell, 0, 1) === '"' && substr($cell, -1) === '"') {
                $cell = substr($cell, 1, -1);
            }
            
            // Render markdown formatting in all cells
            $cell = renderMarkdownInline($cell);
            
            // Apply badge styling to Type column (usually last column)
            $cellClass = '';
            if ($i === count($row) - 1) {
                $typeLower = strtolower(trim(strip_tags($cell)));
                $typeLower = preg_replace('/\s+/', '-', $typeLower);
                $cellClass = ' class="type-badge type-' . $typeLower . '"';
            }
            
            $recommendationsHtml .= '<td' . $cellClass . '>' . $cell . '</td>';
        }
        $recommendationsHtml .= '</tr>';
    }
    
    $recommendationsHtml .= '</tbody></table>';
    fclose($csv_file);
}

// ===== Generate Pages =====
// Generate Episodes page (episodes.html)
$template = file_get_contents('base.html');
$html = str_replace('<!-- posts here -->',$outhtml,$template);
file_put_contents('public/episodes.html', $html);

// Generate Tweets page (tweets.html)
$tweetsTemplate = file_get_contents('tweets.html');
$tweetsHtml = str_replace('<!-- tweets here -->',$tweetshtml,$tweetsTemplate);
file_put_contents('public/tweets.html', $tweetsHtml);

// Generate About page (about.html)
$aboutTemplate = file_get_contents('about.html');
$aboutPageHtml = str_replace('<!-- about content here -->',$aboutHtml,$aboutTemplate);
file_put_contents('public/about.html', $aboutPageHtml);

// Generate Recommendations page (recommendations.html)
$recsTemplate = file_get_contents('recommendations.html');
$recsPageHtml = str_replace('<!-- recommendations table here -->',$recommendationsHtml,$recsTemplate);
file_put_contents('public/recommendations.html', $recsPageHtml);

// ===== Helper function to parse markdown =====
function parseMarkdown($text) {
    // Headers
    $text = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $text);
    $text = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $text);
    $text = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $text);
    
    // Bold
    $text = preg_replace('/\*\*(.*?)\*\*/m', '<strong>$1</strong>', $text);
    $text = preg_replace('/__(.+?)__/m', '<strong>$1</strong>', $text);
    
    // Italic
    $text = preg_replace('/\*(.*?)\*/m', '<em>$1</em>', $text);
    $text = preg_replace('/_(.+?)_/m', '<em>$1</em>', $text);
    
    // Links
    $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" target="_blank">$1</a>', $text);
    
    // Horizontal rule
    $text = preg_replace('/^---+$/m', '<hr>', $text);
    
    // Line breaks - convert double newlines to paragraphs
    $paragraphs = preg_split('/\n\n+/', $text);
    $html = '';
    foreach ($paragraphs as $para) {
        $para = trim($para);
        if (empty($para)) continue;
        
        // Check if it's a heading or hr
        if (preg_match('/^<h[1-3]>|^<hr>/', $para)) {
            $html .= $para;
        }
        // Check if it's a list
        else if (preg_match('/^[\*\-\+] /', $para)) {
            $html .= '<ul>';
            $lines = explode("\n", $para);
            foreach ($lines as $line) {
                if (preg_match('/^[\*\-\+] (.+)$/', $line, $matches)) {
                    $html .= '<li>' . $matches[1] . '</li>';
                }
            }
            $html .= '</ul>';
        }
        else {
            $html .= '<p>' . $para . '</p>';
        }
    }
    
    return $html;
}

// ===== Helper function to render inline markdown =====
function renderMarkdownInline($text) {
    // First escape HTML
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    
    // Links: [text](url)
    $text = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" target="_blank">$1</a>', $text);
    
    // Bold: **text** or __text__
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
    
    // Italic: *text* or _text_
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    $text = preg_replace('/_(.+?)_/', '<em>$1</em>', $text);
    
    return $text;
}

?>
