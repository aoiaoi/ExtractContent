# ExtractContent for PHP

## About ExtractContent

   The ExtractContent will extract content from HTML.  
   It has been rewritten in PHP from Ruby and Perl.

   Syuyo Nakatani:: http://labs.cybozu.co.jp/blog/nakatani/downloads/extractcontent.rb  
   Ina Lintaro:: http://search.cpan.org/dist/HTML-ExtractContent/lib/HTML/ExtractContent.pm

## Getting Started
   Clone the repo, git clone git://github.com/aoiaoi/ExtractContent.git: , or download the latest release.

## Performing Basic Usage
### Example #1 Instantiating a ExtractContent object
    
    <?php
    require_once 'ExtractContent.php';

    $extractor = new ExtractContent();
    
### Example #2 Extract content from HTML
    
    $extractor = new ExtractContent();
    $extractor->extract($html);
    echo $extractor->asText();
    // if retrieve as HTML:
    echo $extractor->asHtml();

### Example #3 Extract title from HTML

    $extractor = new ExtractContent();
    $extractor->extract($html);
    echo $extractor->getTitle();
    
### Example #4 Set parameters

    $extractor = new ExtractContent(array('g_adsense' => true));
    
    // This is actually exactly the same:
    $extractor = new ExtractContent();
    $extractor->setOptions('g_adsense' => true);

## Configuration Parameters

   threshold          | integer 60  
   min_length         | integer 30  
   decay_factor       | integer 0.75  
   no_body_factor     | integer 0.72  
   continuous_factor  | integer 1.62  
   punctuation_weight | integer 10  
   min_nolink         | integer 8  
   nolist_ratio       | integer 0.2  
   g_adsense          | boolean false  
   debug              | boolian false  

## Available Public Methods

   ExtractContent::setOptions(array $options);  
   ExtractContent::extract(string $html);  
   ExtractContent::getTitle();  
   ExtractContent::asText();  
   ExtractContent::asHtml();  

## Development Environment
   
   PHP 5.4.1-rc2

## Copyright
   
   Copyright (c) 2012 aoiaoi. All rights reserved.
   
### Copyright of the original implementation
   
   Copyright (c) 2007-2012 Nakatani Shuyo / Cybozu Labs Inc. All rights reserved.

## License
   
   The files in this archive are released under the New BSD license. You can find a copy of this license in LICENSE.txt
   