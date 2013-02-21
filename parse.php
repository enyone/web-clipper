<?php

  /** web-clipper
  * @author Juho Tykkälä, http://www.enymind.fi/
  * @copyright 2013 Juho Tykkälä
  * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
  * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License, version 2 (one or other)
  * @version 1.0.0
  */

  # Prevent direct
  if(!isset($_GET["force"]))
  {
    header("Location: /");
    die();
  }

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
    return trim( str_replace( array( "&nbsp;", "&NBSP;", "\t", "\\u00a0" ), " ", $string ) );
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
    if( !($html = file_get_html( $site->url )) )
      continue;

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
            $lines[$lineIndex] = b_trim( strip_tags($line) );
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
<!DOCTYPE html>
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php print $config["page.title"]." - " . date($config["page.dateformat"]); ?></title>
  <link rel="stylesheet" type="text/css" href="style.css" />
  <link rel="stylesheet" type="text/css" href="jquery.mobile.css">
  <script type="text/javascript" src="jquery.js"></script>
  <script type="text/javascript" src="jquery.mobile.js"></script>
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
    function bold( food ) {
      $("div.none").each( function() { $(this).trigger('collapse'); } );
      if( food != prevFood ) {
        $("p."+food).each( function() { $(this).parent().trigger('expand'); } );
        prevFood = food;
      }
      else
        prevFood = "";
    }

  </script>
</head>
<body>
<div data-role="page">
<?php

  # Print header
  print "<div data-role=\"header\"><h1>".$config["page.title"];
  print " @ " . date($config["page.dateformat"]) . "</h1></div>\n";
  
  print "<div style=\"margin-left: 10px; text-align: center; clear: both\">\n";

  # Print filter buttons
  foreach( $filters as $filter ) {
    print "<button data-inline=\"true\" data-icon=\"check\" class=\"bull ".$filter->parent."\" onclick=\"bold('".$filter->parent."');\">".ucfirst($filter->parent)."</button>\n";
  }

  # Print "all" button
  print "<button data-inline=\"true\" data-icon=\"star\" onclick=\"bold('all');\">Kaikki</button></div>\n";

  # Init looping and columns
  $JQMColDiv = array( 2 => "a", 3 => "b", 4 => "c", 5 => "d" );
  $JQMColCla = array( 1 => "a", 2 => "b", 3 => "c", 4 => "d", 5 => "e" );
  $count = 1;
  $perCol = ceil( count( $sites ) / intval($config["divs.columns"]) );
  print "<div class=\"ui-grid-" . $JQMColDiv[$config["divs.columns"]] . "\">";

  # If shuffle
  if($config["divs.shuffle"])
    shuffle( $sites );

  # LOOP
  foreach( $sites as $site ) {
  
    # Is collapsed
    $isCollapsed = "true";
    if(isset($_GET["uncollapsed"]))
      $isCollapsed = "false";
  
    # Box header
    print "<div class=\"ui-block-" . $JQMColCla[$count] . "\">";
    print "<div class=\"none\" data-role=\"collapsible\" data-content-theme=\"c\" data-collapsed=\"" . $isCollapsed . "\" data-theme=\"b\">";
    print "<h3>" . $site->name . "</h3>\n";

    $first = true;

    # Loop through separated lines
    foreach( $site->foods as $food ) {

      $filterClasses = "";
      foreach($food->filterMatches as $filter) {
        $filterClasses .= " " . $filter->parent;
      }

      print "<p class=\"all " . $filterClasses . "\">";

      $plaintext = $food->description;

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

      print "</p>";

      # Next line is no more the first line
      $first = false;
    }

    print "<a style=\"float: right; top: -11px;\" href=\"" . $site->url . "\" data-mini=\"true\" data-icon=\"arrow-r\" data-role=\"button\" data-iconpos=\"right\" data-inline=\"true\">" . $site->name . "</a></div></div>\n";
    $count++;

    if( $count > $config["divs.columns"] )
      $count = 1;
  }

  print "</div>";

?>
</div>
</body>
</html>
<?php

  }

  # HTML mixed PHP ends
  #################################

?>
