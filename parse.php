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
  function a_strpos( $haystack, $needles )
  {
    foreach( $needles as $needle )
    {
      if( strpos( $haystack, $needle ) !== false )
        return $needle;
    }
    return false;
  }

  # b_trim function (trim also whitespace html entities)
  function b_trim( $string )
  {
    return trim( str_replace( array( "&nbsp;", "&NBSP;" ), "", $string ) );
  }

  # get_color_bulls function (to add filter markings to content divs)
  function get_color_bulls( $string, $config, $filters )
  {
    $text = "";

    foreach( $filters as $parent => $words )
    {
      # If filter-word found in string
      if( a_strpos( $string, $words ) )
        $text .= "<span class=\"bull " . $parent . "\">&raquo;</span> ";
    }
    
    # If none of the filters matched and not a price-line
    if( empty( $text ) && !a_strpos( $string, $config["currencies"] ) )
      $text .= "<span class=\"bull none\">&raquo;</span> ";
  
    return $text;
  }


  #################################
  # Code begins

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
  $sql = "SELECT name, url, selector, subselector, sameline, brbreak FROM sources WHERE enabled = 1 ORDER BY name";
  $result = mysql_query_and_check($sql, true);

  # Fetch and store all source sited from database
  $sites = array();
  while ($row = mysql_fetch_assoc($result)) {
    $sites[] = $row;
  }
  mysql_free_result($result);

  # Query all filters
  $sql = "SELECT parent, word FROM synonyms ORDER BY parent";
  $result = mysql_query_and_check($sql, false);

  # Fetch and store all filters from database
  $filters = array();
  while ($row = mysql_fetch_assoc($result)) {
    $filters[$row['parent']][] = $row['word'];
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
  # HTML mixed PHP begins
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
foreach( $filters as $parent => $words ) {
  print "<span class=\"bull ".$parent."\" onclick=\"bold('".$parent."');\">".ucfirst($parent)."</span>";
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
foreach( $sites as $site )
{
  if( $count > 0 && $count % $perCol == 0 )
    print "</div><div class=\"col\">";
  
  # Box header
  print "<div class=\"content\">
	 <div class=\"name\">
	 <a href=\"" . $site["url"] . "\" target=\"_blank\">" . utf8_encode( $site["name"] ) . "</a>
	 </div>\n";

  # Get HTML source from site url
  $html = file_get_html( $site["url"] );

  # Find and select desired selector from source
  $ret = $html->find( $site["selector"], 0 );

  if( !empty( $site["subselector"] ) )
  {
    if( $ret == null )
    {
      print "\"<span class=\"error\">" . $site["selector"] . "\" not found!</span></div>";
      continue;
    }

    # Are we trying to separate source lines with br-tag or with subselector
    if( $site["brbreak"] )
    {
      # TODO: fix this to separate lines correctly on br-tag
      $currText = $ret->innertext;
      $currText = explode( "<br>", $currText );
    }
    else
      $ret = $ret->find( $site["subselector"] );
    
    $first = true;
    $fetch = false;

    # Loop through separated lines
    foreach( $ret as $line )
    {
      # Dunno, just works on some pages...
      if( a_strpos( $line->plaintext, array(0xC4, 0xE4, 0xD6, 0xF6) ) )
        $plaintext = utf8_encode( b_trim( preg_replace("/\s+/", " ", $line->plaintext ) ) );
      else
        $plaintext = b_trim( preg_replace("/\s+/", " ", $line->plaintext ) );

      # Figure out next weekday number (sunday=0)
      $nextWDay = date("w") + 1;
      if( $nextWDay > 6 )
        $nextWDay = 0;

      # If current weekday found on line, begin fetching..
      if( strpos( strtolower( $plaintext ), $config["weekdays"][ date("w") ] ) !== false )
        $fetch = true;
      # ..and when next weekday is found, end fetching.
      else if( strpos( strtolower( $plaintext ), $config["weekdays"][ $nextWDay ] ) !== false )
        $fetch = false;

      # Continue, if this is something we do not want
      if( !$fetch && !isset( $_GET["all"] ) )
        continue;

      $text = trim( $plaintext );
      $text = preg_replace("/[^a-zA-Z0-9\s]/", "", $text);

      if( !empty( $text ) )
      {
        # If line contains one of the weekdays
        if( $got = a_strpos( strtolower( $plaintext ), $config["weekdays"] ) ) {
          print ( ( $site["sameline"] ) ? get_color_bulls( strtolower( $plaintext ), $config, $filters ) : "" );
          print ($first ? "" : "<br />");

          # Print line itself
          print preg_replace( "/".$got."/", "<b>" . $weekdayStrings[ $got ] . "</b>", strtolower( $plaintext ) );
          print "<br />\n";
        }
        else {
          print get_color_bulls( strtolower( $plaintext ), $config, $filters );

          # Print line itself
          print $plaintext;
          print "<br />\n";
        }
      }

      # Next line is no more the first line
      $first = false;
    }
  }

  print "</div>\n";
  $count++;
}

print "</div>";

?>
<div style="clear: both;"></div>
</body>
</html>
