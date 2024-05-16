<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Finder.ResearchSearch
 *
 * @copyright   Copyright (C) NPEU 2024.
 * @license     MIT License; see LICENSE.md
 */

namespace NPEU\Plugin\Finder\ResearchSearch\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Table\Table;
use Joomla\Component\Finder\Administrator\Indexer\Adapter;
use Joomla\Component\Finder\Administrator\Indexer\Helper;
use Joomla\Component\Finder\Administrator\Indexer\Indexer;
use Joomla\Component\Finder\Administrator\Indexer\Result;
use Joomla\Component\Weblinks\Site\Helper\RouteHelper;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\DatabaseQuery;
use Joomla\Event\DispatcherInterface;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;


/**
 * Allows indexing of certain Research modules.
 */
final class ResearchSearch extends Adapter
{
    use DatabaseAwareTrait;

    /**
     * An internal flag whether plugin should listen any event.
     *
     * @var bool
     *
     * @since   4.3.0
     */
    protected static $enabled = false;

    /**
     * The plugin identifier.
     *
     * @var    string
     * @since  2.5
     */
    protected $context = 'ResearchSearch';

    /**
     * The extension name.
     *
     * @var    string
     * @since  2.5
     */
    protected $extension = 'com_researchprojects';

    /**
     * The sublayout to use when rendering the results.
     *
     * @var    string
     * @since  2.5
     */
    #protected $layout = 'weblink';

    /**
     * The type of content that the adapter indexes.
     *
     * @var    string
     * @since  2.5
     */
    protected $type_title = 'ResearchSearch';

    /**
     * The table name.
     *
     * @var    string
     * @since  2.5
     */
    #protected $table = '#__research';

    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  3.1
     */
    protected $autoloadLanguage = true;

    /**
     * Constructor
     *
     * @param   DispatcherInterface  $dispatcher
     * @param   array                $config
     * @param   DatabaseInterface    $database
     */
    public function __construct(DispatcherInterface $dispatcher, array $config, DatabaseInterface $database)
    {
        self::$enabled = true;

        parent::__construct($dispatcher, $config);

        $this->setDatabase($database);
    }

    /**
     * Method to get a list of content items to index.
     *
     * @param   integer         $offset  The list offset.
     * @param   integer         $limit   The list limit.
     * @param   QueryInterface  $query   A QueryInterface object. [optional]
     *
     * @return  Result[]  An array of Result objects.
     *
     * @since   2.5
     * @throws  \Exception on database error.
     */
    protected function getItems($offset, $limit, $query = null)
    {
        $items = [];

        // Get the content items to index.
        $this->db->setQuery($this->getListQuery($query), $offset, $limit);
        $rows = $this->db->loadAssocList();

        // Convert the items to result objects.
        foreach ($rows as $row) {
            // Convert the item to a result object.
            $item = ArrayHelper::toObject($row, Result::class);

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
     * Method to index an item. The item must be a Result object.
     *
     * @param   Result  $item  The item to index as an Result object.
     *
     * @return  void
     *
     * @throws  \Exception on database error.
     * @since   2.5
     */
    protected function index(Result $item)
    {

        // Check if the extension is enabled
        if (ComponentHelper::isEnabled($this->extension) == false) {
            return;
        }

        $item->setLanguage();
        $this->indexer->index($item);
    }

    /**
     * Method to setup the indexer to be run.
     *
     * @return  boolean  True on success.
     *
     * @since   2.5
     */
    protected function setup()
    {
        return true;
    }

    /**
     * Method to get the SQL query used to retrieve the list of content items.
     *
     * @param   mixed  $query  A JDatabaseQuery object or null.
     *
     * @return  DatabaseQuery  A database object.
     *
     * @since   2.5
     */
    protected function getListQuery($query = null)
    {
        $db = $this->getDatabase();

        // Check if we can use the supplied SQL query.
        $query = $query instanceof DatabaseQuery ? $query : $db->getQuery(true)
            ->select('a.id, a.title, a.alias, a.content, a.created AS start_date')
            ->from('#__researchprojects AS a')
            ->where('a.state = 1');

        return $query;
    }

}