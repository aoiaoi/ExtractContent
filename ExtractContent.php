<?php

class ExtractContent {
  protected $_title   = null;
  protected $_content = null;
  
  protected $_options = array('threshold'          => 60,
			      'min_length'         => 30,
			      'decay_factor'       => 0.75,
			      'no_body_factor'     => 0.72,
			      'continuous_factor'  => 1.62,
			      'punctuation_weight' => 10,
			      'min_nolink'         => 8,
			      'nolist_ratio'       => 0.2,
			      'g_adsense'          => false,
			      'debug'              => false);
  
  protected $_patterns = array('a'        => "/<a\s[^>]*>.*?<\/a\s*>/is",
			       'href'     => "/<a\s+href\s*=\s*(['\"]?)(?:[^\"'\s]+)\\1/is",
			       'list'     => "/<(ul|dl|ol)(.+)<\/\\1>/is",
			       'li'       => "/(?:<li[^>]*>|<dd[^>]*>)/is",
			       'title'    => "/<title[^>]*>(.*?)<\/title\s*>/is",
			       'headline' => "/(<h\d\s*>\s*(.*?)\s*<\/h\d\s*>)/is",
			       'head'     => "/<head[^>]*>.*?<\/head\s*>/is",
			       'alt'      => "/<img\s[^>]*alt\s*=\s*['\"]?(.*?)[\"']?[^>]*>/is",
			       'comment'  => "/(?:<!--.*?-->|<([^>\s]+)[^>]*\s+style=['\"]?[^>'\"]*(?:display:\s*none|visibility:\s*hidden)[^>'\"]*['\"]?[^>]*>.*?<\/\\1\s*>)/is",
			       'special'  => "/<![A-Za-z].*?>/is",
			       'useless'  => array("/<(script|style|select|noscript)[^>]*>.*?<\/\\1\s*>/is",
						   "/<div\s[^>]*(?:id|class)\s*=\s*['\"]?\S*(?:more|menu|side|navi)\S*[\"']?[^>]*>/is"),
			       
			       'punctuations'          => "/(?:[。、．，！？]|\.[^A-Za-z0-9]|,[^0-9]|!|\?)/uis",
			       'nocontent'             => "/<\/frameset>|<meta\s+http-equiv\s*=\s*[\"']?refresh['\"]?[^>]*url/is",
			       'waste_expressions'     => "/Copyright|All\s*Rights?\s*Reserved?/is",
			       'affiliate_expressions' => "/amazon[a-z0-9\.\/\-\?&]+-22/is",
			       'block_separator'       => "/<\/?(?:div|center|td)[^>]*>|<p\s*[^>]*class\s*=\s*[\"']?(?:posted|plugin-\w+)['\"]?[^>]*>/is",
			       
			       'g_adsense_ignore'      => "/<!--\s*google_ad_section_start\(weight=ignore\)\s*-->.*?<!--\s*google_ad_section_end.*?-->/sm",
			       'g_adsense'             => "/<!--\s*google_ad_section_start[^>]*-->.*?<!--\s*google_ad_section_end.*?-->/sm"
			       );
  
  public function __construct($options = null) {
    if (null !== $options) {
      $this->setOptions($options);
    }
  }
  
  public function setOptions($options = array()) {
    if (! is_array($options)) {
      throw new Exception('Array object expected, got ' . gettype($options));
    }

    $this->_options = array_merge($this->_options, $options);
    
    return $this;
  }
  
  public function extract($html) {
    if (! is_string($html) || empty($html)) {
      throw new Exception('HTML contents must be in a string.');
    }

    if (preg_match($this->_patterns['nocontent'], $html, $matches)) {
      $this->_content = '';
      
      return $this;
    }
    
    $html = $this->_extractTitle($html);
    $html = $this->_eliminateHead($html);
    
    if ($this->_options['g_adsense']) {
      $html = $this->_extractGoogleAdSenseSectionTarget($html);
    }
    
    $html = $this->_eliminateUselessSymbols($html);
    $html = $this->_eliminateUselessTags($html);
    
    $factor     = 1.0;
    $continuous = 1.0;
    $body       = '';
    $score      = 0;
    $best       = array('content' => '', 'score' => 0);
    $flag       = 0;
    
    $blockElements = preg_split($this->_patterns['block_separator'], $html);
    
    foreach ($blockElements as $block) {
      $block = trim($block);

      if (! $this->_decode($block)) {
	continue;
      }
      
      if (mb_strlen($body) > 0) {
	$continuous /= $this->_options['continuous_factor'];
      }
      
      $noLink = $this->_eliminateLinks($block);      
      
      if (mb_strlen($noLink) < $this->_options['min_length']) {
	continue;
      }
      
      $c = $this->_calculateScore($noLink, $factor);
      $factor *= $this->_options['decay_factor'];
      
      $noBodyRate = $this->_noBodyRate($block);
      
      $c *= pow($this->_options['no_body_factor'], $noBodyRate);
      $c1 = $c * $continuous;
      
      if ($c1 > $this->_options['threshold']) {
	$flag = 1;
	
	if ($this->_options['debug']) {
	  echo "\n---- continue {$c} * {$continuous} = {$c1} " . mb_strlen($noLink) . "\n\n{$block}\n";
	}
	
	$body .= $block . "\n";
	$score += $c1;
	$continuous = $this->_options['continuous_factor'];
	
      } elseif ($c > $this->_options['threshold']) {
	$flag = 1;
	
	if ($this->_options['debug']) { echo "\n---- end of cluster: {$score}\n"; }
	
	if ($score > $best['score']) {
	  if ($this->_options['debug']) { echo "!!!! best: score = {$score}\n"; }
	  
	  $best = array('content' => $body, 'score' => $score);
	}
	
	if ($this->_options['debug']) { echo "\n"; }
	
	$body = $block . "\n";
	$score = $c;
	$continuous = $this->_options['continuous_factor'];
	
	if ($this->_options['debug']) {
	  echo "\n---- continue {$c} * {$continuous} = {$c1} " . mb_strlen($noLink). "\n\n{$block}\n";
	}
	
      } else {
	if (! $flag) {
	  $factor /= $this->_options['decay_factor'];
	}
	
	if ($this->_options['debug']) {
	  echo "\n>> reject {$c} * {$continuous} = {$c1} " . mb_strlen($noLink) . "\n{$block}\n<< reject\n";
	}
      }
    }
    
    if ($this->_options['debug']) { echo "\n---- end of cluster: {$score}\n"; }
    
    if ($best['score'] < $score) {
      if ($this->_options['debug']) { echo "!!!! best: score = {$score}\n"; }
      
      $best = array('content' => $body, 'score' => $score);
    }
    
    $this->_content = $best['content'];
    
    return $this;
  }
  
  public function getTitle() {
    return $this->_title;
  }
  
  public function asHtml() {
    return $this->_content;
  }
  
  public function asText() {
    return $this->_toText($this->_content);
  }
  
  protected function _extractTitle($html) {
    if (preg_match($this->_patterns['title'], $html, $matches)) {
      $title = trim(strip_tags($matches[1]));
      
      $this->_title = $title;
      
      if (mb_strlen($title)) {
	$html = preg_replace_callback($this->_patterns['headline'],
				      function($m) use ($title) {
					if ($needle = trim(strip_tags($m[2]))) {
					  $position = mb_strpos($title, $needle);
					} else {
					  $position = 0;
				        }
				        return ($position !== false) ? "<div>{$m[2]}</div>" : $m[1];
				      },
				      $html);
      }
    }
    
    return $html;
  }
  
  protected function _eliminateHead($html) {
    return preg_replace($this->_patterns['head'], '', $html);
  }
  
  protected function _extractGoogleAdSenseSectionTarget($html) {
    preg_replace($this->_patterns['g_adsense_ignore'], '', $html);
    
    if (preg_match($this->_patterns['g_adsense'], $html, $matches)) {
      $html = $matches[0];
    }
    
    return $html;
  }
  
  protected function _eliminateUselessSymbols($html) {
    $html = preg_replace($this->_patterns['comment'], '', $html);
    $html = preg_replace($this->_patterns['special'], '', $html);
    
    return $html;
  }

  protected function _eliminateUselessTags($html) {
    foreach ($this->_patterns['useless'] as $pattern) {
      $html = preg_replace($pattern, '', $html);
    }
    
    return $html;
  }
    
  protected function _eliminateLinks($block) {
    $count = $this->_countPatternMatches($this->_patterns['a'], $block);
    $noLink = $this->_toText($this->_eliminateForms($this->_eliminateAnchor($block)));
    
    if (mb_strlen($noLink) < $this->_options['min_nolink'] * $count) {
      return '';
    }
    
    if ($this->_isLinkList($block)) {
      return '';
    }
    
    return $noLink;
  }

  protected function _isLinkList($block) {
    if (preg_match($this->_patterns['list'], $block, $matches)) {
      $list = $matches[2];
      
      $fragments = preg_split($this->_patterns['list'], $block, 2, PREG_SPLIT_DELIM_CAPTURE);
      
      $noList = $list;
      preg_replace($this->_patterns['list'], '', $noList);
      
      $noList = $this->_toText(implode($noList, $fragments));
      
      $listItems = preg_split($this->_patterns['li'], $list);
      array_shift($listItems);
      
      $rate = 0;
      
      foreach ($listItems as $li) {
	if (preg_match($this->_patterns['href'], $li, $matches)) {
	  $rate++;
	}
      }
      
      if (0 !== count($listItems)) {
	$rate = 1.0 * $rate / (count($listItems) + 1);
      }
      
      $list = $this->_toText($list);
      $limit = ($this->_options['nolist_ratio'] * $rate) * ($rate * mb_strlen($list));
      
      return mb_strlen($noList) < $limit;
    }
    
    return false;
  }

  protected function _calculateScore($noLink, $factor) {
    return (mb_strlen($noLink)
	    + $this->_countPatternMatches($this->_patterns['punctuations'], $noLink)
	    * $this->_options['punctuation_weight'])
      * $factor;
  }
  
  protected function _noBodyRate($block) {
    return $this->_countPatternMatches($this->_patterns['waste_expressions'], $block)
      + $this->_countPatternMatches($this->_patterns['affiliate_expressions'], $block) / 2.0;
  }
  
  
  protected function _decode($string) {
    return trim($this->_reduceWhiteSpace(html_entity_decode(strip_tags($string))));
  }
  
  protected function _reduceWhiteSpace($string) {
    $string = preg_replace("/[ \t]+/", ' ', $string);
    $string = preg_replace("/\n\s*/s", "\n", $string);
    
    return $string;
  }

  protected function _eliminateAnchor($string) {
    return $this->_eliminateTags($string, 'a');
  }
  
  protected function _eliminateForms($string) {
    return $this->_eliminateTags($string, 'form');
  }

  protected function _eliminateTags($string, $tag) {
    return preg_replace("/<{$tag}[\s>].*?<\/{$tag}\s*>/is", '', $string);
  }

  protected function _toText($string) {
    return $this->_decode($this->_extractAlt($string));
  }
  
  protected function _extractAlt($string) {
    return preg_replace_callback($this->_patterns['alt'], function($m) { return $m[1]; } , $string);
  }

  protected function _countPatternMatches($regexp, $string) {
    return preg_match($regexp, $string, $matches);
  }
}