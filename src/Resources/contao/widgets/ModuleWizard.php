<?php
/*
 * Posts'n'pages extension for Contao Open Source CMS.
 *
 * @copyright  Arne Stappen (alias aGoat) 2021
 * @package    contao-postsnpages
 * @author     Arne Stappen <mehh@agoat.xyz>
 * @link       https://agoat.xyz
 * @license    LGPL-3.0
 */

namespace Agoat\PostsnPagesBundle\Contao;


use Contao\Backend;
use Contao\Image;
use Contao\Input;
use Contao\ModuleWizard as ContaoModuleWizard;
use Contao\StringUtil;

/**
 * Provide methods to handle modules of a page layout
 */
class ModuleWizard extends ContaoModuleWizard
{

    /**
     * Generate the widget and return it as string
     *
     * @return string
     */
    public function generate()
    {
        $this->import('Database');

        $arrButtons = ['edit', 'copy', 'delete', 'enable', 'drag'];

        // Get all modules of the current theme
        $objModules =
            $this->Database->prepare("SELECT id, name, type FROM tl_module WHERE pid=(SELECT pid FROM " . $this->strTable . " WHERE id=?) ORDER BY name"
            )->execute($this->currentRecord);

        // Add the articles module
        $modules[] = ['id' => 0, 'name' => $GLOBALS['TL_LANG']['MOD']['container'][0], 'type' => 'container'];

        if ($objModules->numRows) {
            $modules = array_merge($modules, $objModules->fetchAllAssoc());
        }

        $GLOBALS['TL_LANG']['FMD']['container'] = $GLOBALS['TL_LANG']['MOD']['container'];

        // Add the module type (see #3835)
        foreach ($modules as $k => $v) {
            $v['type'] = $GLOBALS['TL_LANG']['FMD'][$v['type']][0];
            $modules[$k] = $v;
        }

        $objRow =
            $this->Database->prepare("SELECT * FROM " . $this->strTable . " WHERE id=?")
                           ->limit(1)
                           ->execute($this->currentRecord);

        // Show all columns and filter in PageRegular (see #3273)
        $cols = ['header', 'left', 'right', 'main', 'footer'];

        // Add custom layout sections
        if ($objRow->sections != '') {
            $arrSections = StringUtil::deserialize($objRow->sections);

            if (!empty($arrSections) && is_array($arrSections)) {
                foreach ($arrSections as $v) {
                    if (!empty($v['id'])) {
                        $cols[$v['id']] = $v['id'];
                    }
                }
            }
        }

        $cols = Backend::convertLayoutSectionIdsToAssociativeArray($cols);

        // Get the new value
        if (Input::post('FORM_SUBMIT') == $this->strTable) {
            $this->varValue = Input::post($this->strId);
        }

        // Make sure there is at least an empty array
        if (!is_array($this->varValue) || !$this->varValue[0]) {
            $this->varValue = [['mod' => 0, 'col' => 'main']];
        } else {
            $arrCols = [];

            // Initialize the sorting order
            foreach ($cols as $col) {
                $arrCols[$col] = [];
            }

            foreach ($this->varValue as $v) {
                $arrCols[$v['col']][] = $v;
            }

            $this->varValue = [];

            foreach ($arrCols as $arrCol) {
                $this->varValue = array_merge($this->varValue, $arrCol);
            }
        }

        // Add the label and the return wizard
        $return = '<table id="ctrl_' . $this->strId . '" class="tl_modulewizard">
  <thead>
  <tr>
    <th>' . $GLOBALS['TL_LANG']['MSC']['mw_module'] . '</th>
    <th>' . $GLOBALS['TL_LANG']['MSC']['mw_column'] . '</th>
    <th></th>
  </tr>
  </thead>
  <tbody class="sortable">';

        // Add the input fields
        for ($i = 0, $c = count($this->varValue); $i < $c; $i++) {
            $options = '';

            // Add modules
            foreach ($modules as $v) {
                $options .= '<option value="' . StringUtil::specialchars($v['id']
                    ) . '"' . static::optionSelected($v['id'],
                        $this->varValue[$i]['mod']
                    ) . '>' . $v['name'] . ' [' . $v['type'] . ']</option>';
            }

            $return .= '
  <tr>
    <td><select name="' . $this->strId . '[' . $i . '][mod]" class="tl_select tl_chosen" onfocus="Backend.getScrollOffset()" onchange="Backend.updateModuleLink(this)">' . $options . '</select></td>';

            $options = '';

            // Add columns
            foreach ($cols as $k => $v) {
                $options .= '<option value="' . StringUtil::specialchars($k) . '"' . static::optionSelected($k,
                        $this->varValue[$i]['col']
                    ) . '>' . $v . '</option>';
            }

            $return .= '
    <td><select name="' . $this->strId . '[' . $i . '][col]" class="tl_select_column" onfocus="Backend.getScrollOffset()">' . $options . '</select></td>
    <td>';

            // Add buttons
            foreach ($arrButtons as $button) {
                if ($button == 'edit') {
                    $return .= ' <a href="contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->varValue[$i]['mod'] . '&amp;popup=1&amp;nb=1&amp;rt=' . REQUEST_TOKEN . '" title="' . \StringUtil::specialchars($GLOBALS['TL_LANG']['tl_layout']['edit_module']
                        ) . '" class="module_link" ' . (($this->varValue[$i]['mod'] > 0) ? '' : ' style="display:none"') . ' onclick="Backend.openModalIframe({\'title\':\'' . StringUtil::specialchars(str_replace("'",
                            "\\'",
                            $GLOBALS['TL_LANG']['tl_layout']['edit_module']
                        )
                        ) . '\',\'url\':this.href});return false">' . Image::getHtml('edit.svg'
                        ) . '</a>' . Image::getHtml('edit_.svg',
                            '',
                            'class="module_image"' . (($this->varValue[$i]['mod'] > 0) ? ' style="display:none"' : '')
                        );
                } elseif ($button == 'drag') {
                    $return .= ' <button type="button" class="drag-handle" title="' . StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['move']
                        ) . '">' . Image::getHtml('drag.svg') . '</button>';
                } elseif ($button == 'enable') {
                    $return .= ' <button type="button" data-command="enable" class="mw_enable" title="' . StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['mw_enable']
                        ) . '">' . Image::getHtml((($this->varValue[$i]['enable']) ? 'visible.svg' : 'invisible.svg')
                        ) . '</button><input name="' . $this->strId . '[' . $i . '][enable]" type="checkbox" class="tl_checkbox mw_enable" value="1" onfocus="Backend.getScrollOffset()"' . (($this->varValue[$i]['enable']) ? ' checked' : '') . '>';
                } else {
                    $return .= ' <button type="button" data-command="' . $button . '" title="' . StringUtil::specialchars($GLOBALS['TL_LANG']['MSC']['mw_' . $button]
                        ) . '">' . Image::getHtml($button . '.svg') . '</button>';
                }
            }

            $return .= '</td>
  </tr>';
        }

        return $return . '
  </tbody>
  </table>
  <script>Backend.moduleWizard("ctrl_' . $this->strId . '")</script>';
    }

}
