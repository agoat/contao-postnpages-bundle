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


use Contao\Backend;
use Contao\BackendUser;
use Contao\Config;
use Contao\CoreBundle\Exception\AccessDeniedException;
use Contao\Image;
use Contao\Input;
use Contao\PageModel;
use Contao\StringUtil;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

$this->loadDataContainer('tl_page');

$GLOBALS['TL_DCA']['tl_archive'] = [
    // Config
    'config'      => [
        'dataContainer'    => 'TableExtended',
        'ptable'           => 'tl_page',
        'ctable'           => ['tl_post'],
        'switchToEdit'     => true,
        'enableVersioning' => true,
        'onload_callback'  => [
            ['tl_archive', 'checkPermission'],
            ['tl_archive', 'addCustomLayoutSectionReferences'],
            ['tl_page', 'addBreadcrumb'],
        ],
        'sql'              => [
            'keys' => [
                'id'  => 'primary',
                'pid' => 'index',
            ],
        ],
    ],

    // List
    'list'        => [
        'sorting'           => [
            'mode'                  => 6,
            'fields'                => ['title'],
            'paste_button_callback' => ['tl_archive', 'pasteArchiv'],
            'panelLayout'           => 'filter;search',
            'pfilter'               => ['type=?', 'post'],
        ],
        'label'             => [
            'fields'         => ['title'],
            'label_callback' => ['tl_archive', 'addIcon'],
        ],
        'global_operations' => [
            'toggleNodes' => [
                'label'        => &$GLOBALS['TL_LANG']['MSC']['toggleAll'],
                'href'         => '&amp;ptg=all',
                'class'        => 'header_toggle',
                'showOnSelect' => true,
            ],
            'all'         => [
                'label'      => &$GLOBALS['TL_LANG']['MSC']['all'],
                'href'       => 'act=select',
                'class'      => 'header_edit_all',
                'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
            ],
        ],
        'operations'        => [
            'edit'       => [
                'label'           => &$GLOBALS['TL_LANG']['tl_archive']['edit'],
                'href'            => 'table=tl_post',
                'icon'            => 'edit.svg',
                'button_callback' => ['tl_archive', 'editArchiv'],
            ],
            'editheader' => [
                'label'           => &$GLOBALS['TL_LANG']['tl_archive']['editheader'],
                'href'            => 'act=edit',
                'icon'            => 'header.svg',
                'button_callback' => ['tl_archive', 'editHeader'],
            ],
            'copy'       => [
                'label'           => &$GLOBALS['TL_LANG']['tl_archive']['copy'],
                'href'            => 'act=paste&amp;mode=copy',
                'icon'            => 'copy.svg',
                'attributes'      => 'onclick="Backend.getScrollOffset()"',
                'button_callback' => ['tl_archive', 'copyArchiv'],
            ],
            'cut'        => [
                'label'           => &$GLOBALS['TL_LANG']['tl_archive']['cut'],
                'href'            => 'act=paste&amp;mode=cut',
                'icon'            => 'cut.svg',
                'attributes'      => 'onclick="Backend.getScrollOffset()"',
                'button_callback' => ['tl_archive', 'cutArchiv'],
            ],
            'delete'     => [
                'label'           => &$GLOBALS['TL_LANG']['tl_archive']['delete'],
                'href'            => 'act=delete',
                'icon'            => 'delete.svg',
                'attributes'      => 'onclick="if(!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\'))return false;Backend.getScrollOffset()"',
                'button_callback' => ['tl_archive', 'deleteArchiv'],
            ],
            'show'       => [
                'label' => &$GLOBALS['TL_LANG']['tl_archive']['show'],
                'href'  => 'act=show',
                'icon'  => 'show.svg',
            ],
        ],
    ],

    // Palettes
    'palettes'    => [
        '__selector__' => ['protected'],
        'default'      => '{title_legend},title;{protected_legend:hide},protected',
    ],

    // Subpalettes
    'subpalettes' => [
        'protected' => 'groups',
    ],

    // Fields
    'fields'      => [
        'id'        => [
            'label'  => ['ID'],
            'search' => true,
            'sql'    => "int(10) unsigned NOT NULL auto_increment",
        ],
        'pid'       => [
            'foreignKey' => 'tl_page.title',
            'sql'        => "int(10) unsigned NOT NULL default '0'",
            'relation'   => ['type' => 'belongsTo', 'load' => 'lazy'],
        ],
        'tstamp'    => [
            'sql' => "int(10) unsigned NOT NULL default '0'",
        ],
        'title'     => [
            'label'     => &$GLOBALS['TL_LANG']['tl_archive']['title'],
            'exclude'   => true,
            'inputType' => 'text',
            'search'    => true,
            'eval'      => ['mandatory' => true, 'decodeEntities' => true, 'maxlength' => 255, 'tl_class' => 'w50'],
            'sql'       => "varchar(255) NOT NULL default ''",
        ],
        'protected' => [
            'label'     => &$GLOBALS['TL_LANG']['tl_archive']['protected'],
            'exclude'   => true,
            'filter'    => true,
            'inputType' => 'checkbox',
            'eval'      => ['submitOnChange' => true],
            'sql'       => "char(1) NOT NULL default ''",
        ],
        'groups'    => [
            'label'      => &$GLOBALS['TL_LANG']['tl_archive']['groups'],
            'exclude'    => true,
            'filter'     => true,
            'inputType'  => 'checkbox',
            'foreignKey' => 'tl_member_group.name',
            'eval'       => ['mandatory' => true, 'multiple' => true],
            'sql'        => "blob NULL",
            'relation'   => ['type' => 'hasMany', 'load' => 'lazy'],
        ],
    ],
];

/**
 * Provide miscellaneous methods that are used by the data configuration array.
 *
 * @author Arne Stappen (alias aGoat) <https://agoat.xyz>
 */
class tl_archive extends Backend
{

    /**
     * Import the back end user object
     */
    public function __construct()
    {
        parent::__construct();
        $this->import('BackendUser', 'User');
    }


    /**
     * Check permissions to edit table tl_page
     *
     * @throws Contao\CoreBundle\Exception\AccessDeniedException
     */
    public function checkPermission()
    {
        if ($this->User->isAdmin) {
            return;
        }

        /** @var SessionInterface $objSession */
        $objSession = System::getContainer()->get('session');

        $session = $objSession->all();

        // Set the default page user and group
        $GLOBALS['TL_DCA']['tl_page']['fields']['cuser']['default'] =
            intval(Config::get('defaultUser') ?: $this->User->id);
        $GLOBALS['TL_DCA']['tl_page']['fields']['cgroup']['default'] =
            intval(Config::get('defaultGroup') ?: $this->User->groups[0]);

        // Restrict the page tree
        $GLOBALS['TL_DCA']['tl_page']['list']['sorting']['root'] = $this->User->pagemounts;

        // Set allowed page IDs (edit multiple)
        if (is_array($session['CURRENT']['IDS'])) {
            $edit_all = [];
            $delete_all = [];

            foreach ($session['CURRENT']['IDS'] as $id) {
                $objArticle =
                    $this->Database->prepare("SELECT p.pid, p.includeChmod, p.chmod, p.cuser, p.cgroup FROM tl_archive a, tl_page p WHERE a.id=? AND a.pid=p.id"
                    )->limit(1)->execute($id);

                if ($objArticle->numRows < 1) {
                    continue;
                }

                $row = $objArticle->row();

                if ($this->User->isAllowed(BackendUser::CAN_EDIT_ARTICLES, $row)) {
                    $edit_all[] = $id;
                }

                if ($this->User->isAllowed(BackendUser::CAN_DELETE_ARTICLES, $row)) {
                    $delete_all[] = $id;
                }
            }

            $session['CURRENT']['IDS'] = (Input::get('act') == 'deleteAll') ? $delete_all : $edit_all;
        }

        // Set allowed clipboard IDs
        if (isset($session['CLIPBOARD']['tl_archive']) && is_array($session['CLIPBOARD']['tl_archive']['id'])) {
            $clipboard = [];

            foreach ($session['CLIPBOARD']['tl_archive']['id'] as $id) {
                $objArticle =
                    $this->Database->prepare("SELECT p.pid, p.includeChmod, p.chmod, p.cuser, p.cgroup FROM tl_archive a, tl_page p WHERE a.id=? AND a.pid=p.id"
                    )->limit(1)->execute($id);

                if ($objArticle->numRows < 1) {
                    continue;
                }

                if ($this->User->isAllowed(BackendUser::CAN_EDIT_ARTICLE_HIERARCHY, $objArticle->row())) {
                    $clipboard[] = $id;
                }
            }

            $session['CLIPBOARD']['tl_archive']['id'] = $clipboard;
        }

        $permission = 0;

        // Overwrite the session
        $objSession->replace($session);

        // Check current action
        if (Input::get('act') && Input::get('act') != 'paste') {
            // Set ID of the article's page
            $objPage =
                $this->Database->prepare("SELECT pid FROM tl_archive WHERE id=?")->limit(1)->execute(Input::get('id'));

            $ids = $objPage->numRows ? [$objPage->pid] : [];

            // Set permission
            switch (Input::get('act')) {
                case 'edit':
                case 'toggle':
                    $permission = BackendUser::CAN_EDIT_ARTICLES;
                    break;

                case 'move':
                    $permission = BackendUser::CAN_EDIT_ARTICLE_HIERARCHY;
                    $ids[] = Input::get('sid');
                    break;

                // Do not insert articles into a website root page
                case 'create':
                case 'copy':
                case 'copyAll':
                case 'cut':
                case 'cutAll':
                    $permission = BackendUser::CAN_EDIT_ARTICLE_HIERARCHY;

                    // Insert into a page
                    if (Input::get('mode') == 2) {
                        $objParent =
                            $this->Database->prepare("SELECT id, type FROM tl_page WHERE id=?")
                                           ->limit(1)
                                           ->execute(Input::get('pid'));

                        $ids[] = Input::get('pid');
                    } // Insert after an article
                    else {
                        $objParent =
                            $this->Database->prepare("SELECT id, type FROM tl_page WHERE id=(SELECT pid FROM tl_archive WHERE id=?)"
                            )->limit(1)->execute(Input::get('pid'));

                        $ids[] = $objParent->id;
                    }

                    if ($objParent->numRows && $objParent->type == 'root') {
                        throw new AccessDeniedException('Attempt to insert an article into website root page ID ' . Input::get('pid'
                            ) . '.'
                        );
                    }
                    break;

                case 'delete':
                    $permission = BackendUser::CAN_DELETE_ARTICLES;
                    break;
            }

            // Check user permissions
            if (Input::get('act') != 'show') {
                $pagemounts = [];

                // Get all allowed pages for the current user
                foreach ($this->User->pagemounts as $root) {
                    $pagemounts[] = $root;
                    $pagemounts = array_merge($pagemounts, $this->Database->getChildRecords($root, 'tl_page'));
                }

                $pagemounts = array_unique($pagemounts);

                // Check each page
                foreach ($ids as $id) {
                    if (!in_array($id, $pagemounts)) {
                        throw new AccessDeniedException('Page ID ' . $id . ' is not mounted.');
                    }

                    $objPage = $this->Database->prepare("SELECT * FROM tl_page WHERE id=?")->limit(1)->execute($id);

                    // Check whether the current user has permission for the current page
                    if ($objPage->numRows && !$this->User->isAllowed($permission, $objPage->row())) {
                        throw new AccessDeniedException('Not enough permissions to ' . Input::get('act'
                            ) . ' ' . (strlen(Input::get('id')) ? 'article ID ' . Input::get('id'
                                ) : ' articles') . ' on page ID ' . $id . ' or to paste it/them into page ID ' . $id . '.'
                        );
                    }
                }
            }
        }
    }


    /**
     * Add an image to each page in the tree
     *
     * @param  array  $row
     * @param  string  $label
     *
     * @return string
     */
    public function addIcon($row, $label)
    {
        $image = 'archive.svg';

        if ($row['protected']) {
            $image = 'archive_4.svg';
        }

        return '<a>' . Image::getHtml('bundles/agoatpostsnpages/' . $image, '', '') . '</a> ' . $label;
    }


    /**
     * Return the edit article button
     *
     * @param  array  $row
     * @param  string  $href
     * @param  string  $label
     * @param  string  $title
     * @param  string  $icon
     * @param  string  $attributes
     *
     * @return string
     */
    public function editArchiv($row, $href, $label, $title, $icon, $attributes)
    {
        $objPage = PageModel::findById($row['pid']);

        return $this->User->isAllowed(BackendUser::CAN_EDIT_ARTICLES,
            $objPage->row()
        ) ? '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']
            ) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon,
                $label
            ) . '</a> ' : Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)) . ' ';
    }


    /**
     * Return the edit header button
     *
     * @param  array  $row
     * @param  string  $href
     * @param  string  $label
     * @param  string  $title
     * @param  string  $icon
     * @param  string  $attributes
     *
     * @return string
     */
    public function editHeader($row, $href, $label, $title, $icon, $attributes)
    {
        if (!$this->User->canEditFieldsOf('tl_archive')) {
            return Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)) . ' ';
        }

        $objPage = PageModel::findById($row['pid']);

        return $this->User->isAllowed(BackendUser::CAN_EDIT_ARTICLES,
            $objPage->row()
        ) ? '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']
            ) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon,
                $label
            ) . '</a> ' : Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)) . ' ';
    }


    /**
     * Return the copy article button
     *
     * @param  array  $row
     * @param  string  $href
     * @param  string  $label
     * @param  string  $title
     * @param  string  $icon
     * @param  string  $attributes
     * @param  string  $table
     *
     * @return string
     */
    public function copyArchiv($row, $href, $label, $title, $icon, $attributes, $table)
    {
        if ($GLOBALS['TL_DCA'][$table]['config']['closed']) {
            return '';
        }

        $objPage = PageModel::findById($row['pid']);

        return $this->User->isAllowed(BackendUser::CAN_EDIT_ARTICLE_HIERARCHY,
            $objPage->row()
        ) ? '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']
            ) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon,
                $label
            ) . '</a> ' : Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)) . ' ';
    }


    /**
     * Return the cut article button
     *
     * @param  array  $row
     * @param  string  $href
     * @param  string  $label
     * @param  string  $title
     * @param  string  $icon
     * @param  string  $attributes
     *
     * @return string
     */
    public function cutArchiv($row, $href, $label, $title, $icon, $attributes)
    {
        $objPage = PageModel::findById($row['pid']);

        return $this->User->isAllowed(BackendUser::CAN_EDIT_ARTICLE_HIERARCHY,
            $objPage->row()
        ) ? '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']
            ) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon,
                $label
            ) . '</a> ' : Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)) . ' ';
    }


    /**
     * Return the paste article button
     *
     * @param  DataContainer  $dc
     * @param  array  $row
     * @param  string  $table
     * @param  boolean  $cr
     * @param  array  $arrClipboard
     *
     * @return string
     */
    public function pasteArchiv(DataContainer $dc, $row, $table, $cr, $arrClipboard = null)
    {
        $imagePasteAfter =
            Image::getHtml('pasteafter.svg', sprintf($GLOBALS['TL_LANG'][$dc->table]['pasteafter'][1], $row['id']));
        $imagePasteInto =
            Image::getHtml('pasteinto.svg', sprintf($GLOBALS['TL_LANG'][$dc->table]['pasteinto'][1], $row['id']));

        if ($table != $GLOBALS['TL_DCA'][$dc->table]['config']['ptable'] || !$this->User->isAllowed(BackendUser::CAN_EDIT_ARTICLE_HIERARCHY,
                $row
            ) || $row['type'] != 'post' || $cr) {
            return Image::getHtml('pasteinto_.svg') . ' ';
        } else {
            return '<a href="' . $this->addToUrl('act=' . $arrClipboard['mode'] . '&amp;mode=2&amp;pid=' . $row['id'] . (!is_array($arrClipboard['id']
                    ) ? '&amp;id=' . $arrClipboard['id'] : '')
                ) . '" title="' . StringUtil::specialchars(sprintf($GLOBALS['TL_LANG'][$dc->table]['pasteinto'][1],
                        $row['id']
                    )
                ) . '" onclick="Backend.getScrollOffset()">' . $imagePasteInto . '</a> ';
        }
    }


    /**
     * Return the delete article button
     *
     * @param  array  $row
     * @param  string  $href
     * @param  string  $label
     * @param  string  $title
     * @param  string  $icon
     * @param  string  $attributes
     *
     * @return string
     */
    public function deleteArchiv($row, $href, $label, $title, $icon, $attributes)
    {
        $objPage = PageModel::findById($row['pid']);

        return $this->User->isAllowed(BackendUser::CAN_DELETE_ARTICLES,
            $objPage->row()
        ) ? '<a href="' . $this->addToUrl($href . '&amp;id=' . $row['id']
            ) . '" title="' . StringUtil::specialchars($title) . '"' . $attributes . '>' . Image::getHtml($icon,
                $label
            ) . '</a> ' : Image::getHtml(preg_replace('/\.svg$/i', '_.svg', $icon)) . ' ';
    }

}
