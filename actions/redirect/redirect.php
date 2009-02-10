<?php
/**
 * Redirect the user to another, existing, wiki page.
 * 
 * ACL for the page have precedence, therefore the user will not be redirected 
 * if he is not allowed to see the page. The redirect only occurs if the method is 'show'.
 * Append 'redirect=no' as a param to the page URL to be not redirected.
 * 
 * To indicate a temporary redirect, use 'temporary=yes' as an action param. 
 * The default type of redirect is 'Moved permanently'.
 * 
 * @usage		{{redirect page="HomePage" [temporary="yes"] }}
 * @package		Actions
 * @version		$Id$
 *
 * @author		NilsLindenberg
 * @license 	http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @filesource
 * 
 * @input		string $page mandatory: target wiki page
 * @input		bool $temporary	optional: indiacte a temporary redirect
 * 
 * @uses		Wakka::cleanUrl()
 * @uses		Wakka::existsPage
 * @uses		Wakka::GetSafeVar()
 * @uses		Wakka::Link()
 * @uses		Wakka::Redirect()
 * 
 * @todo		test
 * @todo		move i18n constants to language file
 */

// i18n
if (!defined('PAGE_MOVED_TO')) define('PAGE_MOVED_TO', 'This page has been moved to %s.'); # %s - targe page
if (!defined('REDIRECTED_FROM')) define('REDIRECTED_FROM', 'Redirected from %s.'); # %s - redirecting page
if(!defined('INVALID_REDIRECT')) define('INVALID_REDIRECT', 'Invalid redirect. Target must be an existing wiki page.');

// defaults
$headercode = "HTTP/1.0 301 Moved Permanently";
$redirect = TRUE;
$page = '';
$target = '';

// only redirect if we show the page
if('show' != $this->handler)
{
	$redirect = FALSE;
}

// do not redirect when 'redirect=no' is appended to the pages URL.
$stop_redirect = $this->GetSafeVar('redirect');
if(null != $stop_redirect)
{
	$redirect = FALSE;
} 
 

// getting params
if (is_array($vars))
{
    foreach ($vars as $param => $value)
    {
    	if ($param == 'target' || $param == 'to') 
    	{
    		if ($this->existsPage($value)) $target = $value;
    	}
    	if ($param == 'temporary')
    	{
    		$headercode = "HTTP/1.0 302 Moved Temporarily";
    	}	
    }
}

$full_target = $this->Href('',$target, 'redirect=no');
$full_target = str_replace('&amp;', '&', $full_target); # workaround for Href masking & in urls

//the actual redirect  	
if($redirect && '' != $full_target)
{
	header($headercode);
	#$message = sprintf(REDIRECTED_FROM, $this->Link($this->tag));
	$message = sprintf(REDIRECTED_FROM, $this->tag);
	$this->Redirect($full_target, $message);
}

// only display a link 
else 
{
	if ('' != $target)	
	{
		printf(PAGE_MOVED_TO, $this->Link($target)); 	
	}
	else
	{
		echo '<em class="error">'.INVALID_REDIRECT.'</em>';
	}
}
?>