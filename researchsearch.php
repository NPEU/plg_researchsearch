<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Finder.ResearchSearch
 *
 * @copyright   Copyright (C) NPEU 2019.
 * @license     MIT License; see LICENSE.md
 */

defined('_JEXEC') or die;

require_once JPATH_ADMINISTRATOR . '/components/com_finder/helpers/indexer/adapter.php';

/**
 * Indexes research profiles from the Programme of Work database.
 */
class plgFinderResearchSearch extends FinderIndexerAdapter
{
    protected $autoloadLanguage = true;

    /**
     * The extension name.
     *
     * @var    string
     */
    protected $extension = 'com_researchbrowser';

    /**
     * The type of content that the adapter indexes.
     *
     * @var    string
     */
    protected $type_title = 'Researchsearch';

    /**
     * Method to instantiate the indexer adapter.
     *
     * @param   object  &$subject  The object to observe.
     * @param   array   $config    An array that holds the plugin configuration.
     *
     */
    public function __construct(&$subject, $config)
    {

        parent::__construct($subject, $config);
        $this->loadLanguage();
    }

    /**
     * Method to setup the adapter before indexing.
     *
     * @return  boolean  True on success, false on failure.
     *
     * @throws  Exception on database error.
     */
    protected function setup()
    {
        return true;
    }

    /**
     * Method to index an item.
     *
     * @param   FinderIndexerResult  $item  The item to index as a FinderIndexerResult object.
     *
     * @return  boolean  True on success.
     *
     * @throws  Exception on database error.
     */
    protected function index(FinderIndexerResult $item)
    {
        // Check if the extension is enabled
        if (JComponentHelper::isEnabled($this->extension) == false) {
            return;
        }

        $this->indexer->index($item);
    }

    /**
     * Method to get the SQL query used to retrieve the list of content items.
     *
     * @param   mixed  $query  A JDatabaseQuery object. [optional]
     *
     * @return  JDatabaseQuery  A database object.
     */
    protected function getListQuery($sql = null)
    {
        $db = JFactory::getDbo();
        
        // Check if we can use the supplied SQL query.
        $query = $query instanceof JDatabaseQuery ? $query : $db->getQuery(true)
            ->select('a.id, a.title, a.alias, a.content, a.created AS start_date')
            ->from('#__researchprojects AS a')
            ->where('a.state = 1');

        return $query;
    }

    /**
     * Method to get a list of content items to index.
     *
     * @param   integer         $offset  The list offset.
     * @param   integer         $limit   The list limit.
     * @param   JDatabaseQuery  $query   A JDatabaseQuery object. [optional]
     *
     * @return  array  An array of FinderIndexerResult objects.
     *
     * @throws  Exception on database error.
     */
    protected function getItems($offset, $limit, $query = null)
    {
        $items = array();

        // Get the content items to index.
        $this->db->setQuery($this->getListQuery($query), $offset, $limit);
        $rows = $this->db->loadAssocList();

        // Convert the items to result objects.
        foreach ($rows as $row) {
            // Convert the item to a result object.
            $item = JArrayHelper::toObject($row, 'FinderIndexerResult');

            // Sort out endcoding stuff:
            #$item->summary  = $this->utf8_convert($item->summary);

            // Set the item type.
            $item->type_id = $this->type_id;

            // Set the mime type.
            $item->mime = $this->mime;

            // Set the item layout.
            $item->layout = $this->layout;

            // Set the extension if present
            if (isset($row->extension)) {
                $item->extension = $row->extension;
            }

            // Create a useful summary to display:
            $item->summary = $item->title . ': ' . $item->content;

            $item->url    = '/research/projects/' . $item->id . '-' . $item->alias;
            $item->route  = '/research/projects/' . $item->id . '-' . $item->alias;
            $item->state  = 1;
            $item->access = 1;

            // Add the item to the stack.
            $items[] = $item;
        }

        return $items;
    }

    /**
     * Method to convert utf8 characters.
     *
     * @param   string   $text  The text to convert.
     *
     * @return  string
     *
     * @since   2.5
     */
    protected function utf8_convert($text)
    {
        if (!is_string($text)) {
            trigger_error('Function \'utf8_convert\' expects argument 1 to be a string', E_USER_ERROR);
            return false;
        }
        // Only do the slow convert if there are 8-bit characters
        // Avoid using 0xA0 (\240) in ereg ranges. RH73 does not like that
        if (!preg_match("[\200-\237]", $text) && !preg_match("[\241-\377]", $text)) {
            return $text;
        }
        // Decode three byte unicode characters
        $text = preg_replace("/([\340-\357])([\200-\277])([\200-\277])/e", "'&#'.((ord('\\1')-224)*4096 + (ord('\\2')-128)*64 + (ord('\\3')-128)).';'", $text);
        // Decode two byte unicode characters
        $text = preg_replace("/([\300-\337])([\200-\277])/e", "'&#'.((ord('\\1')-192)*64+(ord('\\2')-128)).';'", $text);
        return $text;
    }
}