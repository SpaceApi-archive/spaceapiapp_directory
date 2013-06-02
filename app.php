<?php

//********************************************************************
// do not edit this section

if(!defined("APPSDIR"))
    die("Direct access is not allowed!");

$app_dir = realpath(dirname(__FILE__));
// remove the full path of the document root
$app_dir = str_replace(ROOTDIR, "", $app_dir);

$page->setActivePage(basename($app_dir));

//********************************************************************


$page->addStylesheet("$app_dir/css/style.css");


for($i=8; $i<13; $i++)
{
    $page->addPrefetchAsset("http://" . SITE_URL . "/$app_dir/img/bullet_green_$i.png");
    $page->addPrefetchAsset("http://" . SITE_URL . "/$app_dir/img/bullet_red_$i.png");
}


// define the auto class loader
function class_loader($classname)
{
    $classfile = CLASSDIR . "$classname.class.php";
    
    if (file_exists($classfile))
    {
        require_once($classfile);
        return true;
    }
    
    // this is not so ideal, when the config cannot be loaded this fails
    // so just be sure the Config class is always included!
    $logger = KLogger::instance(LOGDIR, DEBUG_LEVEL);
    $logger->logEmerg("The class '$classname' cannot be loaded!");
    
    return false;
}

spl_autoload_register("class_loader");

// whenever the backend classes are used, we most probably need the logger and the SAPI constant
$logger = KLogger::instance(LOGDIR, DEBUG_LEVEL);
define('SAPI', 'apache');
    
$html = <<<HTML

    <section>
        <h2>Directory</h2>
        
        <p>
            The directory is a big list of hackerspaces URLs that provide information like contact information, the door status or even sensor data such as the temperature or the amount of computers that have currently leased an IP. In the following a <em>hackerspace URL</em> is simply called <em>endpoint</em>.
        </p>
        
        <p>
            If you are a developer who wants to write an application based on the <strong>Space API</strong>, you can fetch the directory directly from this website.
            
            <pre><code><a href="directory.json">http://%s/directory.json</a></pre></code>
        </p>
        
        <p>
            Some <strong>Space API</strong> versions are not downwards compatible. If you need endpoints that have a specific version implemented you can filter those out with the following URL.
            
            <pre><code><a href="directory.json?api=0.13">http://%s/directory.json?api=0.13</a></pre></code>
        </p>
        
        <p>
            There's more. If you write an application which is only interested in endpoints that provide webcam streams you should use the <strong>filter</strong> option.
            
            <pre><code><a href="directory.json?filter=cam">http://%s/directory.json?filter=cam</a></pre></code> 
        </p>
        
        <p>
            There's more to discover. <a href="documentation#Filters">Learn more about the filters</a>.
        </p>
        
    </section>

HTML;

// sprintf is used here because we need to render a constant into the heredoc, PHP doesn't allow this natively.
// Another way could have been to copy the constant to a variable but this variable is possibly messing up with
// the global scope.
$page->addContent(sprintf($html, SITE_URL, SITE_URL, SITE_URL));

$public_directory_path = DIRECTORYDIR . "directory.json.public";

// we can be quite sure that the file exists, but it's better to check
// its existence just for the case that something goes totally wrong.
if( file_exists($public_directory_path))
{
    $public_directory = file_get_contents($public_directory_path);
    $public_directory = json_decode($public_directory, true);
    
    // check if we successfully decoded the json, else initialize an empty array
    if ( is_null($public_directory) || $public_directory === FALSE )
        $public_directory = array();
}

// get the space names and sort them
Utils::ksort($public_directory, true);
$space_names = array_keys($public_directory);

// calculate the amount of elements per column
$amounts = amount_per_column($public_directory, 4);

// contains the <li>foo</li> tags, the array indices
// address the column
$list_item_tags = array();

foreach($amounts as $column_number => $amount)
{
    // initialize the $column_number-th element to avoid an "undefined offset" notice
    // when we concatenate the list item tags
    $list_item_tags[$column_number] = "";

    for($i=0; $i < $amount; $i++)
    {
        // take the top element out of the space name array
        $space_name = array_shift($space_names);
        $space_endpoint = $public_directory[$space_name];

        $sanitized_space_name = NiceFileName::get($space_name);
        $space_file = "$sanitized_space_name.json";
        $space_file_cached = file_get_contents(STATUSCACHEDIR . $space_file);
        $space_api_file = new SpaceApiFile($space_file_cached);

        $obj = $space_api_file->json();
        if(property_exists($obj, "url"))
            $url = $obj->url;
        else
            $url = "#";

        $bullet_version = str_replace("0.", "", $space_api_file->version());

        // we have to create a new instance before calling validate(), otherwise the script runs extremely
        // long, maybe because plugins are registered multiple times (which basically was fixed)
        $space_api_validator = new SpaceApiValidator;
        $bullet_color = $space_api_validator->validate($space_api_file) ? "green" : "red";
        //$bullet_color = "green";

        $class = "version-$bullet_version-$bullet_color";

        $list_element = "<li class=\"$class\"><a href=\"$url\" target=\"_blank\">$space_name</a></li>";

        $list_item_tags[$column_number] .= $list_element;
    }
}

// create the visuals of the space list
$space_list = "";
foreach($list_item_tags as $column)
    $space_list .= '<div class="span3"><ul>'. $column .'</ul></div>';

$html = <<<HTML

    <section>
        <h2>Participating hackerspaces</h2>
        
        <p>
            <ul>
                <li>A red icon means that there are errors in the Space API implementation.</li>
                <li>The number specifies the implemented version.</li>
            </ul>
        </p>
        
        <div class="row">
            $space_list
        </div>
    </section>
HTML;

$page->addContent($html);
