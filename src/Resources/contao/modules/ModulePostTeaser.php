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

use Contao\BackendTemplate;
use Contao\Config;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Environment;
use Contao\Input;
use Contao\Pagination;
use Patchwork\Utf8;


/**
 * ModulePostsTeaser class
 */
class ModulePostTeaser extends ModulePost
{

    /**
     * Template
     *
     * @var string
     */
    protected $strTemplate = 'mod_postteaser';


    /**
     * Do not render the module if a post is called directly
     *
     * @return string
     */
    public function generate()
    {
        if (TL_MODE == 'BE') {
            /** @var BackendTemplate $objTemplate */
            $objTemplate = new BackendTemplate('be_wildcard');

            $objTemplate->wildcard = '### ' . Utf8::strtoupper($GLOBALS['TL_LANG']['FMD']['postteaser'][0]) . ' ###';
            $objTemplate->title = $this->headline;
            $objTemplate->id = $this->id;
            $objTemplate->link = $this->name;
            $objTemplate->href = 'contao/main.php?do=themes&amp;table=tl_module&amp;act=edit&amp;id=' . $this->id;

            return $objTemplate->parse();
        }

        // Don't show teasers when a post is called directly
        if ((isset($_GET['posts']) || (Config::get('useAutoItem') && isset($_GET['auto_item'])))) {
            return '';
        }

        return parent::generate();
    }


    /**
     * Generate the module
     */
    protected function compile()
    {
        // Get published posts
        $objPosts = $this->getPosts();

        if ($objPosts === null) {
            return;
        }

        // Set custom post template
        $this->postTemplate = $this->teaserTpl;

        $arrPosts = [];

        if ($objPosts !== null) {
            while ($objPosts->next()) {
                // Render the teasers
                $arrPosts[] = $this->renderPost($objPosts->current(), true, false);
            }
        }

        if ($this->perPage > 0) {
            // Add the pagination menu
            $objPagination = new Pagination($this->numberOfItems ? min($this->numberOfItems - $this->skipFirst, $this->totalPosts) : $this->totalPosts, $this->perPage, Config::get('maxPaginationLinks'), $id = 'page_n' . $this->id);
            $this->Template->pagination = $objPagination->generate("\n  ");
        }

        if ($this->sortPosts === 'random') {
            shuffle($arrPosts);
        }

        $this->Template->posts = $arrPosts;
    }

}
