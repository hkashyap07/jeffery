<?php
/**
 * Wordpress Table Builder Utility Class.
 *
 * A group of classes designed to make it easier and quicker to create tables
 * within wordpress plugins for the admin section. Using this class should hopefully
 * reduce development and debugging time.
 *
 * This code is very much in alpha phase, and should not be distributed with plugins
 * other than by Dan Harrison.
 *
 * @author Dan Harrison of WP Doctors (http://www.wpdoctors.co.uk)
 *
 * Version History
 *
 * V0.01 				 - Initial version released.
 * V0.02 				 - Added CSS capability to data row.
 * V0.03 - 25th Jul 2013 - Added ability to add custom row objects that do different things.
 * V0.04 - 2nd Aug 2013 - Fix to handle WP 3.6 strict mode.
 */

/**
 * Class that represents a HTML table for the Wordpress admin area.
 */
if (!class_exists('TableBuilder')) {
    class TableBuilder
    {
        /**
     * An array of HTML attributes to apply to the table.
     *
     * @var Array A list of (attribute name => attribute value) pairs.
     */
    public $attributes;

    /**
     * The internal list of columns.
     *
     * @var Array
     */
    private $columnList;

    /**
     * The internal list of rows.
     *
     * @var Array
     */
    private $rowList;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->columnList = array();
        $this->rowList = array();
    }

    /**
     * Add the specified column to the table builder.
     *
     * @param TableColumn $column The column to add to the table.
     */
    public function addColumn($column)
    {
        array_push($this->columnList, $column);
    }

    /**
     * Add the specified row to the table builder.
     *
     * @param Array  $data     The row to add to the table.
     * @param String $rowClass The CSS class for the row, or false if there isn't one.
     */
    public function addRow($data, $rowClass = false)
    {
        $newRow = new RowData($data, $rowClass);
        $this->rowList[] = $newRow;
    }

    /**
     * Directly add a new row data object.
     *
     * @param RowData $rowDataObj The row object to add.
     */
    public function addRowObj($rowDataObj)
    {
        $this->rowList[] = $rowDataObj;
    }

    /**
     * Empty the list of row data.
     */
    public function emptyData()
    {
        $this->rowList = array();
    }

    /**
     * Generates the HTML for the table object.
     *
     * @return String The HTML for this table object.
     */
    public function toString()
    {

        // Determine attributes and add them to the end of the table
        $attributeString = '';
        $attr_class_set = false; // Flag to determine if class already been set.

        if (count($this->attributes) > 0) {
            foreach ($this->attributes as $aname => $avalue) {
                $attributeString .= "$aname=\"$avalue\" ";
            }
        }

        // Set default class of widefat if no class has been specified.
        if (!$attr_class_set) {
            $attributeString .= 'class="widefat"';
        }

        $resultString = "\n<table $attributeString>";

        // Print the column header and footer
        $columnHeader = "\t<tr>";
        foreach ($this->columnList as $columnObj) {
            $columnHeader .= "\n\t\t".$columnObj->toHeaderString().'';
        }
        $columnHeader .= "\n\t</tr>";

        // Use same header for both header and footer.
        $resultString .= "\n<thead>\n$columnHeader\n</thead>";
        $resultString .= "\n<tfoot>\n$columnHeader\n</tfoot>";

        // Do the table body
        $resultString .= "\n<tbody>";

        // Handle columns for each row
        foreach ($this->rowList as $rowDataObj) {
            // Delegate rendering to the row object.
            $resultString .= $rowDataObj->toString($this->columnList);
        }

        // Close remaining tags
        $resultString .= "\n</tbody>";
        $resultString .= "\n</table>";

        return $resultString;
    }
    }

/**
 * Class that represents a single row of data in the table.
 */
class RowData
{
    /**
     * The list of data entries as key => value.
     *
     * @var Array
     */
    private $dataList;

    /**
     * The CSS class for the data row (Default is false).
     *
     * @var String
     */
    private $rowClass;

    /**
     * Constructor.
     */
    public function __construct($data, $rowClass = false)
    {
        $this->dataList = $data;
        $this->rowClass = $rowClass;
    }

    /**
     * Return the list of data entries as a key => value array.
     *
     * @return Array
     */
    public function getRowData()
    {
        return $this->dataList;
    }

    /**
     * Returns the row class (or false if there isn't one).
     *
     * @return String
     */
    public function getRowClass()
    {
        return $this->rowClass;
    }

    /**
     * Given the column data, render this single table row.
     *
     * @param Array $theColumnList The list of columns that correspond to this table row.
     *
     * @return String The HTML for the column.
     */
    public function toString($theColumnList)
    {
        $resultString = false;

        $rowdata = $this->getRowData();
        $rowclass = $this->getRowClass();

        // Add the CSS class for the row if there is one.
        if ($rowclass) {
            $resultString .= sprintf('<tr class="%s">', $rowclass);
        } else {
            $resultString .= '<tr>';
        }

        // Use columns to determine order of data in table
        foreach ($theColumnList as $columnObj) {
            $celldata = '';

            // If there's matching data for this column, add it to cell,
            // otherwise leave the cell empty.
            if (isset($rowdata[$columnObj->columnKey])) {
                $celldata = $rowdata[$columnObj->columnKey];
            }

            // Add HTML
            $resultString .= $columnObj->toCellDataString($celldata);
        }

        $resultString .= '</tr>';

        return $resultString;
    }
}

/**
 * Class that represents a single row of data in the table that
 * typically spans several elements.
 */
class RowDataSimple extends RowData
{
    /**
     * The list of data entries as key => value.
     *
     * @var Array
     */
    private $spanWidth;

    /**
     * The raw HTML to use for the row.
     *
     * @var String
     */
    private $htmlContent;

    /**
     * Create a single table row that has custom HTML content.
     *
     * @param String  $rowClass    The class of the row.
     * @param String  $htmlContent The HTML content to put into the row.
     * @param Integer $spanWidth   How many columns that the row spans.
     */
    public function __construct($rowClass = false, $htmlContent = false, $spanWidth = 1)
    {
        parent::__construct(false, $rowClass); // No array of data here.
        $this->htmlContent = $htmlContent;
        $this->spanWidth = $spanWidth;
    }

    /**
     * Given the column data, render this single table row.
     *
     * @param Array $theColumnList The list of columns that correspond to this table row.
     *
     * @return String The HTML for the column.
     */
    public function toString($theColumnList)
    {
        // Add the CSS class for the row if there is one.
        $resultString = sprintf('<tr class="%s"><td colspan="%s">%s</td></tr>',
        $this->getRowClass(),
        $this->spanWidth,
        $this->htmlContent
        );

        return $resultString;
    }
}

/**
 * Class that represents a table column for use with <code>TableBuilder</code>.
 */
class TableColumn
{
    /**
     * The table title for this column.
     *
     * @var String
     */
    public $columnTitle;

    /**
     * The string that represents this column.
     *
     * @var String
     */
    public $columnKey;

    /**
     * The HTML class associated with a table header cell.
     *
     * @var String
     */
    public $headerClass;

    /**
     * The HTML class associated with an individual table data cell.
     *
     * @var String
     */
    public $cellClass;

    /**
     * Constructor creates a column in the table using the specified column title.
     *
     * @param String $columnTitle The title of the column as used in the HTML.
     * @param String $columnKey   A key used to refer to the column.
     */
    public function __construct($columnTitle, $columnKey)
    {
        $this->columnTitle = $columnTitle;
        $this->columnKey = $columnKey;
    }

    /**
     * Generates the HTML for the header of this <code>TableColumn</code> object.
     *
     * @return String The HTML for the header of this <code>TableColumn</code> object.
     */
    public function toHeaderString()
    {
        $classdata = '';
        if ($this->headerClass) {
            $classdata = 'class="'.$this->headerClass.'"';
        }

        $returnString = '<th id="'.$this->columnKey.'" scope="col" '.$classdata.'>'.$this->columnTitle.'</th>';

        return $returnString;
    }

    /**
     * Converts the specified data into a correctly formatted cell data,
     * using the style associated with this column.
     *
     * @param String $data The data string to insert between &lt;td&gt;&lt;/td&gt; tags.
     *
     * @return String The HTML for the correctly formatted cell data.
     */
     public function toCellDataString($data) {
            $classdata = '';
            if ($this->cellClass) {
                $classdata = 'class="' . $this->cellClass . '"';
            }
            if (is_array($data)) {
                $returnString ='<td ' . $classdata . '><ul>';
                foreach ($data as $key=>$value){
                    $returnString .= "<li>$value</li>";
                }
                $returnString .="</ul></td>";
            } else {
                $returnString = '<td ' . $classdata . '>' . $data . '</td>';
            }
            return $returnString;
        }
}
}
