<?php

  /** web-clipper
  * @author Juho Tykkälä, http://www.enymind.fi/
  * @copyright 2013 Juho Tykkälä
  * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
  * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
  * @version 1.0.0
  */

  # Require config file and other dependencies
  require_once("config.php");
  require_once("dom.php");


  #################################
  # Function definitions

  # MySQL query function
  function mysql_query_and_check($sql, $exitOnNoRows=false) {
    $result = mysql_query($sql);

    # Check if query succeeded
    if (!$result) {
      echo "Could not successfully run query ($sql): " . mysql_error();
      exit;
    }

    # Check if any source sites available
    if ($exitOnNoRows && mysql_num_rows($result) == 0) {
      echo "No rows found, exiting...";
      exit;
    }

    return $result;
  }

  # a_strpos function (find array of needles)
  function a_stripos( $haystack, $needles ) {
    foreach( $needles as $needle )
    {
      if( stripos( $haystack, $needle ) !== false )
        return $needle;
    }
    return false;
  }

  # b_trim function (trim also whitespace html entities)
  function b_trim( $string ) {
    return trim( str_replace( array( "&nbsp;", "&NBSP;" ), " ", $string ) );
  }


  #################################
  # Entity definitions

  class Food {
    public $description;
    public $price;
    public $currency;
    public $filterMatches;
    
    public function __construct() {
      $this->description = "";
      $this->price = 0.0;
      $this->currency = "€";
      $this->filterMatches = array();
    }
    
    public function addFilterMatch( Filter $filter ) {
      $this->filterMatches[] = $filter;
    }
  }
  
  class Filter {
    public $parent;
    public $words;
    
    public function __construct() {
      $this->parent = "";
      $this->words = array();
    }
    
    public function populate($sqlRow) {
      $this->parent = $sqlRow["parent"];
      $this->words = explode(",", $sqlRow["words"]);
    }
  }
  
  class Site {
    public $name;
    public $url;
    public $selector;
    public $subselector;
    public $sameline;
    public $brbreak;
    public $skip;
    public $foods;
    
    public function __construct() {
      $this->name = "";
      $this->url = "";
      $this->selector = "";
      $this->subselector = "";
      $this->sameline = false;
      $this->brbreak = false;
      $this->skip = array();
      $this->foods = array();
    }
    
    public function populate($sqlRow) {
      $this->name = $sqlRow["name"];
      $this->url = $sqlRow["url"];
      $this->selector = $sqlRow["selector"];
      $this->subselector = $sqlRow["subselector"];
      $this->sameline = ( $sqlRow["sameline"] ) ? true : false;
      $this->brbreak = ( $sqlRow["brbreak"] ) ? true : false;
      $this->skip = explode(" ", $sqlRow["skip"]);
    }
    
    public function addFood( Food $food ) {
      $this->foods[] = $food;
    }
    
    public function updatePreviousFoodPrice($price) {
      $this->foods[count($this->foods)-1]->price = $price;
    }
  }


  #################################
  # Init code begins

  # Initialize database connection
  $conn = mysql_connect($config["database.hostname"],
                        $config["database.username"],
                        $config["database.password"]
  );

  # Check if connection made succesfully
  if (!$conn) {
    echo "Unable to connect to database on ".$config["database.hostname"].": " . mysql_error();
    exit;
  }

  # Select database for connection
  if (!mysql_select_db($config["database.name"])) {
    echo "Unable to select database ".$config["database.name"].": " . mysql_error();
    exit;
  }

  # Query all enabled source sites
  $sql = "SELECT name, url, selector, subselector, sameline, brbreak, skip FROM sources WHERE enabled = 1 ORDER BY name";
  $result = mysql_query_and_check($sql, true);

  # Fetch and store all source sited from database
  $sites = array();
  while ($row = mysql_fetch_assoc($result)) {
    $site = new Site();
    $site->populate($row);
    $sites[] = $site;
  }
  mysql_free_result($result);

  # Query all filters
  $sql = "SELECT parent, GROUP_CONCAT(word) as words FROM synonyms GROUP BY parent ORDER BY parent";
  $result = mysql_query_and_check($sql, false);

  # Fetch and store all filters from database
  $filters = array();
  while ($row = mysql_fetch_assoc($result)) {
    $filter = new Filter();
    $filter->populate($row);
    $filters[] = $filter;
  }
  mysql_free_result($result);

  # Populate weekday array and mark today
  $weekdayStrings = array();
  foreach( $config["weekdays"] as $n => $p )
  {
    # If today
    if( $n == date("w") )
      $ps = "<span class=\"today\">" . ucfirst( $p ) . "</span>";
    else
      $ps = ucfirst( $p );
  
    $weekdayStrings[$p] = $ps;
  }


  #################################
  # Parsing code begins

  # LOOP
  foreach( $sites as $siteIndex => $site ) {
    # Get HTML source from site url
    $html = file_get_html( $site->url );

    # Find and select desired selector from source
    $ret = $html->find( $site->selector, 0 );

    if( !empty( $site->subselector ) ) {
      # If no food data found
      if( $ret == null )
        continue;

      $ret = $ret->find( $site->subselector );
      
      $first = true;
      $fetch = false;
      $previousPrice = "0.0";

      # Loop through separated lines
      foreach( $ret as $line ) {
        # Dunno, just works on some pages...
        if( a_stripos( $line->plaintext, array(0xC4, 0xE4, 0xD6, 0xF6) ) )
          $utf8Text = utf8_encode( b_trim( preg_replace("/\s+/", " ", $line->plaintext ) ) );
        else
          $utf8Text = b_trim( preg_replace("/\s+/", " ", $line->plaintext ) );
      
        # Are we trying to separate source lines with br-tag or with subselector
        if( $site->brbreak ) {
          # TODO: fix this to separate lines correctly on br-tag
          $lines = preg_split( "/(\<br(.)\/\>|\<br\>)/", $line->innertext );
          
          foreach($lines as $lineIndex => $line)
            $lines[$lineIndex] = strip_tags($line);
        }
        else
          $lines = array($utf8Text);
      
        foreach( $lines as $line ) {
          $plaintext = $line;
        
          # Figure out next weekday number (sunday=0)
          $nextWDay = date("w") + 1;
          if( $nextWDay > 6 )
            $nextWDay = 0;

          # If current weekday found on line, begin fetching..
          if( strpos( strtolower( $plaintext ), $config["weekdays"][ date("w") ] ) !== false ) {
            $fetch = true;

            # Do not want to add line representing the current day unless same line includes food data
            if(!$site->sameline && !isset($_GET["all"]))
              continue;
          }
          # ..and when next weekday is found, end fetching.
          else if( strpos( strtolower( $plaintext ), $config["weekdays"][ $nextWDay ] ) !== false )
            $fetch = false;
          # ..and if on friday and next monday appears (multi-week list)
          else if( date("w") == 5 && strpos( strtolower( $plaintext ), $config["weekdays"][1] ) !== false )
            $fetch = false;

          # Continue, if this is something we do not want
          if( !$fetch && !isset($_GET["all"]) )
            continue;
          
          # Skip unwanted lines
          if( a_stripos( $plaintext, $site->skip ) )
            continue;
            
          # Tidy string
          $text = trim( $plaintext );
          $text = preg_replace("/[^a-zA-Z0-9\s]/", "", $text);

          # Init new food
          $food = new Food();
          
          if( !empty( $text ) ) {
          
            # Parse food price
            $price = "0.0";
            $currency = $config["currencies"][0];
            $food->currency = $currency;
            
            if( count($config["currencies"]) > 1 ) {
              foreach($config["currencies"] as $curr)
                $plaintext = str_replace($curr, $currency, $plaintext);
            }
            
            if( preg_match('/\d+(?:[\.\,]\d+)?(.)\\'.$currency.'/', $plaintext, $matches) ) {
              $price = str_replace(",", ".", $matches[0]);
              $plaintext = trim(str_replace($matches[0], "", $plaintext));
              
              # If this line includes the price of the previous line
              if( empty($plaintext) && isset($sites[$siteIndex-1]) ) {
                $sites[$siteIndex-1]->updatePreviousFoodPrice(floatval($price));
                continue;
              }
            }
            
            if( $price == "0.0" )
              $price = $previousPrice;
            
            $food->price = floatval($price);
            $food->description = $plaintext;
            
            foreach( $filters as $filter ) {
              # If filter-word found in food description
              if( a_stripos( $food->description, $filter->words ) )
                $food->addFilterMatch($filter);
            }
          }
          
          if( !empty($food->description) )
            $sites[$siteIndex]->addFood($food);

          # Next line is no more the first line
          $first = false;
          
          $previousPrice = $price;
        }
      }
    }
  }

  if(isset($_GET["json"])) {
  
    # Print JSON
    header("Content-type: application/json; charset=UTF-8");
    print json_encode($sites);
    exit();
  }
  else {


  #################################
  # HTML mixed PHP begins
  
  header("Content-type: text/html; charset=UTF-8");
?>
<html>
<head>
<META http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<title><?php print $config["page.title"]." - " . date($config["page.dateformat"]); ?></title>
<link rel="stylesheet" type="text/css" href="style.css" />
<script type="text/javascript">

  // Google analytics
  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', '<?php print $config["googleanalytics.acc"] ?>']);
  _gaq.push(['_trackPageview']);

  // Google analytics
  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();

  // Function to emphasize correspondeing div when filter clicked
  var prevFood = "";
  function bold( food )
  {
    $("span.bull").each( function() { $(this).parent().removeClass("em"); } );
    $("span.bull").each( function() { $(this).removeClass("em"); } );
    if( food != prevFood )
    {
      $("span."+food).parent().addClass("em");
      $("span."+food).addClass("em");
      prevFood = food;
    }
    else
      prevFood = "";
  }

</script>
<script type="text/JavaScript" src="jquery.js"></script> 
</head>
<body>
<?php

  # Print header
  print "<h1>".$config["page.title"]."</h1>
  <h3>@ " . date($config["page.dateformat"]) . "</h3>
  <div style=\"margin-left: 10px;\">";

  # Print filter buttons
  foreach( $filters as $filter ) {
    print "<span class=\"bull ".$filter->parent."\" onclick=\"bold('".$filter->parent."');\">".ucfirst($filter->parent)."</span>";
  }

  # Print "all" button
  print "<a href=\"all.html\"><span class=\"bull\">Kaikki</span></a>
  </div>
  <div style=\"clear: both;\"></div>";

  # Init looping and columns
  $count = 0;
  $perCol = ceil( count( $sites ) / intval($config["divs.columns"]) );
  print "<div class=\"col\">";

  # If shuffle
  if($config["divs.shuffle"])
    shuffle( $sites );

  # LOOP
  foreach( $sites as $site ) {
  
    if( $count > 0 && $count % $perCol == 0 )
      print "</div><div class=\"col\">";
    
    # Box header
    print "<div class=\"content\">
           <div class=\"name\">
           <a href=\"" . $site->url . "\" target=\"_blank\">" . utf8_encode( $site->name ) . "</a>
           </div>\n";

    $first = true;

    # Loop through separated lines
    foreach( $site->foods as $food ) {
    
      $plaintext = $food->description;
      
      # Print filter bulls
      foreach($food->filterMatches as $filter) {
        print "<span class=\"bull " . $filter->parent . "\">&raquo;</span> ";
      }
      
      if(count($food->filterMatches) == 0)
        print "<span class=\"bull none\">&raquo;</span> ";
    
      # If line contains one of the weekdays
      if( $got = a_stripos( strtolower( $plaintext ), $config["weekdays"] ) ) {
        # Print line itself
        print preg_replace( "/".$got."/", "<b>" . $weekdayStrings[ $got ] . "</b>", strtolower( $plaintext ) );
      }
      else {
        # Print line itself
        print $plaintext;
      }
      
      if( $food->price > 0 )
        print " (" . number_format( $food->price, 2) . " " . $food->currency . ")";
      
      print "<br />";

      # Next line is no more the first line
      $first = false;
    }

    print "</div>\n";
    $count++;
  }

  print "</div>";

?>
<div style="clear: both;"></div>
</body>
</html>
<?php

  }

  # HTML mixed PHP ends
  #################################

?>
