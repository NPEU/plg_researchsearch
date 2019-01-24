<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  Finder.ResearchSearch
 *
 * @copyright   Copyright (C) NPEU 2019.
 * @license     MIT License; see LICENSE.md
 */

defined('_JEXEC') or die;

/**
 * Indexes research profiles from the Programme of Work database.
 */
class plgFinderResearchSearch extends JPlugin
{
    protected $autoloadLanguage = true;

    /**
     * The research database object.
     *
     * @var    object
     */
    protected $r_db;

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

        // The folloing file is excluded from the public git repository (.gitignore) to prevent 
        // accidental exposure of database credentials. However, you will need to create that file
        // in the same directory as this file, and it should contain the follow credentials:
        // $database = '[A]';
        // $hostname = '[B]';
        // $username = '[C]';
        // $password = '[D]';
        //
        // if you prefer to store these elsewhere, then the database_credentials.php can instead
        // require another file or indeed any other mechansim of retrieving the credentials, just so
        // long as those four variables are assigned.
        require_once('database_credentials.php');

        try {
            $this->r_db = new PDO("mysql:host=$hostname;dbname=$database", $username, $password, array(
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8;'
            ));
        }
        catch(PDOException $e) {
            echo $e->getMessage();
            exit;
        }
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
        if (JComponentHelper::isEnabled($this->extension) == false)
        {
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
        $sql = '
            SELECT
                pr.project_id AS id,
                pr.title,
                pr.alias,
                pr.date_created AS publish_start_date,
                pr.date_modified AS modified,
                t.html AS summary
            FROM pow_projects pr
            JOIN cms_component_text t ON pr.text_id = t.component_id
            WHERE pr.status_id > 0
            AND pr.included = 1;
        ';
        return $sql;
    }

    /**
     * Method to get the number of content items available to index.
     *
     * @return  integer  The number of content items available to index.
     *
     * @throws  Exception on database error.
     */
    protected function getContentCount()
    {
        $sql = '
            SELECT count(*) as count
            FROM pow_projects pr
            WHERE pr.status_id > 0
            AND pr.included = 1;
        ';
        $stmt = $this->r_db->query($sql);

        if ($result = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $count = (int) $result['count'];
            return $count;
        }

        return 0;
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
    protected function getItems($offset, $limit, $sql = null)
    {
        JLog::add('FinderIndexerAdapter::getItems', JLog::INFO);

        $items = array();

        // Get the content items to index.
        // Missed body
        $stmt = $this->r_db->query($this->getListQuery($sql));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convert the items to result objects.
        foreach ($rows as $row)
        {
            // Convert the item to a result object.
            $item = JArrayHelper::toObject($row, 'FinderIndexerResult');

            // Sort out endcoding stuff:
            $item->summary  = $this->utf8_convert($item->summary);

            // Set the item type.
            $item->type_id = $this->type_id;

            // Set the mime type.
            $item->mime = $this->mime;

            // Set the item layout.
            $item->layout = $this->layout;

            // Set the extension if present
            if (isset($row->extension))
            {
                $item->extension = $row->extension;
            }
            $item->alias  = $item->alias . '-' . $item->id;
            $item->url    = '/research/' . $item->alias;
            $item->route  = '/research/' . $item->alias;
            $item->state  = 1;
            $item->access = 1;

            // Add the item to the stack.
            $items[] = $item;

        }
        return $items;
    }

    /**
     * Method to get a content item to index.
     *
     * @param   integer  $id  The id of the content item.
     *
     * @return  FinderIndexerResult  A FinderIndexerResult object.
     *
     * @throws  Exception on database error.
     */
    protected function getItem($id)
    {
        JLog::add('FinderIndexerAdapter::getItem', JLog::INFO);

        // Get the list query and add the extra WHERE clause.
        $sql = $this->getListQuery();
        $sql = str_replace(';', ' AND pr.project_id = ' . $id . ';');

        $stmt = $this->r_db->query($sql);
        $row  = $stmt->fetch(PDO::FETCH_ASSOC);

        // Convert the item to a result object.
        $item = JArrayHelper::toObject($row, 'FinderIndexerResult');

        // Set the item type.
        $item->type_id = $this->type_id;

        // Set the item layout.
        $item->layout = $this->layout;

        return $item;
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
        if (!ereg("[\200-\237]", $text) && !ereg("[\241-\377]", $text)) {
            return $text;
        }
        // Decode three byte unicode characters
        $text = preg_replace("/([\340-\357])([\200-\277])([\200-\277])/e", "'&#'.((ord('\\1')-224)*4096 + (ord('\\2')-128)*64 + (ord('\\3')-128)).';'", $text);
        // Decode two byte unicode characters
        $text = preg_replace("/([\300-\337])([\200-\277])/e", "'&#'.((ord('\\1')-192)*64+(ord('\\2')-128)).';'", $text);
        return $text;
    }
}