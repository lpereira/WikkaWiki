<?php
/**
 * Show a list of revisions for the page sorted after time.
 * 
 * @package		Handlers
 * @subpackage	Page
 * @version		$Id$
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * 
 * @uses		Wakka::HasAccess()
 * @uses		Wakka::LoadRevisions()
 * @uses		Wakka::Format()
 * @uses		Wakka::FormOpen()
 * @uses		Wakka::GetUser()
 * @uses		Wakka::FormClose()
 * @uses		Wakka::Href()
 * 
 * @todo		move main <div> to templating class
 */
/**
 * i18n
 */
define('BUTTON_RETURN_TO_NODE', 'Return To Node / Cancel');
define('BUTTON_SHOW_DIFFERENCES', 'Show Differences');
define('ERROR_ACL_READ', 'You aren\'t allowed to read this page.');
define('SIMPLE_DIFF', 'Simple Diff');
define('WHEN_BY_WHO', '%1$s by %2$s');
define('REVISIONS_MORE', 'There are more revisions that were not shown here, click the button labelled %s below to view these entries');
define('BUTTON_REVISIONS_MORE', 'Next ...');

$start = intval($this->GetSafeVar('start', 'get'));
if ($start) $start .= ', ';
else $start = '';

echo '<div class="page">'."\n"; //TODO: move to templating class

if ($this->HasAccess("read")) 
{
	if (isset($_GET['start']))
	{
		$start = intval($this->GetSafeVar('start', 'get'));
		$a = intval($this->GetSafeVar('a', 'get'));
		if ($a)
		{
			$pageA = $this->LoadPageById($a);
		}
	}
	$pages = $this->LoadRevisions($this->tag, $start);
	if (isset($pageA) && is_array($pageA) && is_array($pages))
	{
		array_unshift($pages, $pageA);
	}
	// load revisions for this page
	if ($pages)
	{
		$output = $this->FormOpen("diff", "", "get");
		$output .= "<table border=\"0\" cellspacing=\"0\" cellpadding=\"1\">\n";
		$output .= "<tr>\n";
		$output .= '<td><input type="submit" value="'.BUTTON_SHOW_DIFFERENCES.'" /></td>';
		$output .= '<td><input value="1" type="checkbox" checked="checked" name="fastdiff" id="fastdiff" />'."\n".'<label for="fastdiff">'.SIMPLE_DIFF.'</label></td>';
		$output .= "</tr>\n";
		$output .= "</table>\n";
		$output .= "<table border=\"0\" cellspacing=\"0\" cellpadding=\"1\">\n";

		$c = 0;
		foreach ($pages as $page)
		{
			$c++;
			if ($page['note']) $note='['.$this->htmlspecialchars_ent($page['note']).']'; else $note ='';
			$output .= "<tr>";
			$output .= "<td><input type=\"radio\" name=\"a\" value=\"".$page["id"]."\" ".($c == 1 ? "checked=\"checked\"" : "")." /></td>";
			$output .= "<td><input type=\"radio\" name=\"b\" value=\"".$page["id"]."\" ".($c == 2 ? "checked=\"checked\"" : "")." /></td>";
			$output .= '<td>'.sprintf(WHEN_BY_WHO, '<a href="'.$this->Href('show','','time='.urlencode($page["time"])).'">'.$page['time'].'</a>', $this->Link($page["user"])).' <span class="pagenote smaller">'.$note.'</span></td>';
			$output .= "</tr>\n";
		}
		$output .= "</table><br />\n";
		$output .= '<input type="button" value="'.BUTTON_RETURN_TO_NODE.'" onclick="document.location=\''.$this->Href('').'\';" />'."\n";
		$oldest_revision = $this->LoadOldestRevision($this->tag);
		if ($oldest_revision['id'] != $page['id'])
		{
			$output .= '<input type="hidden" name="start" value="'.$c.'" />'."\n";
			$output .= '<br />'.sprintf(REVISIONS_MORE, BUTTON_REVISIONS_MORE);
			$output .= "\n".'<br /><input type="submit" name="more_revisions" value="'.BUTTON_REVISIONS_MORE.'" onclick=\'this.form.action="'.$this->Href('revisions').'"; return (true);\' />';
		}
		$output .= $this->FormClose()."\n";
	}
	print($output);
} 
else 
{
	print('<em class="error">'.ERROR_ACL_READ.'</em>');
}
echo '</div>'."\n" //TODO: move to templating class
?>
