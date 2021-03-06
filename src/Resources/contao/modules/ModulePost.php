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

use Agoat\PostsnPagesBundle\Model\ArchiveModel;
use Agoat\PostsnPagesBundle\Model\PostModel;
use Agoat\PostsnPagesBundle\Model\TagsModel;
use Contao\CommentsModel;
use Contao\CoreBundle\Exception\PageNotFoundException;
use Contao\Date;
use Contao\Environment;
use Contao\FilesModel;
use Contao\FrontendTemplate;
use Contao\Input;
use Contao\Model\Collection;
use Contao\Module;
use Contao\ModuleModel;
use Contao\PageModel;
use Contao\StringUtil;
use Contao\UserModel;


/**
 * Abstract ModulePost class
 */
abstract class ModulePost extends Module
{

    /**
     * Return posts in consideration of the selection procedures
     *
     * @return Collection|PostModel|Null
     */
    protected function getPosts()
    {
        /** @var PageModel $objPage */
        global $objPage;

        // Show the posts from particular archive(s)
        if (empty($varPids = StringUtil::deserialize($this->archive))) {
            $objArchives = ArchiveModel::findByPid($objPage->id);

            if (null === $objArchives) {
                return null;
            }

            $varPids = $objArchives->fetchEach('id');
        }

        // Handle featured articles
        if ($this->featured == 'featured_posts') {
            $blnFeatured = true;
        } elseif ($this->featured == 'unfeatured_posts') {
            $blnFeatured = false;
        } else {
            $blnFeatured = null;
        }

        // Handle category filter
        if ($this->filterByCategory) {
            $strCategory = $this->category;
        }

        $this->totalPosts = $hardLimit = PostModel::countPublishedByArchivesAndFeaturedAndCategory($varPids, $blnFeatured, $strCategory);

        $arrOptions = [];

        // Handle sorting
        if ($this->sortPosts !== 'random') {
            $arrOptions['order'] = $this->sortPosts . ' ' . (($this->sortOrder == 'descending') ? 'DESC' : 'ASC');
        }

        // Maximum number of items
        if ($this->numberOfItems > 0) {
            $arrOptions['limit'] = intval($this->numberOfItems);
            $hardLimit = min($arrOptions['limit'], $this->totalPosts);
        }

        // Skip items
        if ($this->skipFirst > 0) {
            $arrOptions['offset'] = intval($this->skipFirst);
        }

        // Items per page
        if ($this->perPage > 0) {
            // Get the current page
            $id = 'page_n' . $this->id;
            $page = Input::get($id) ?? 1;

            // Throw error if the page number is out of range
            if ($page < 1 || $page > max(ceil($hardLimit / $this->perPage), 1)) {
                throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
            }

            $arrOptions['offset'] += (max($page, 1) - 1) * $this->perPage;
            $arrOptions['limit'] = intval($this->perPage);

            // Overall limit
            if ($arrOptions['offset'] + $arrOptions['limit'] >= $hardLimit) {
                $arrOptions['limit'] = $hardLimit - $arrOptions['offset'];
            }
        }

        // Return published articles
        return PostModel::findPublishedByArchivesAndFeaturedAndCategory($varPids,
            $blnFeatured,
            $strCategory,
            $arrOptions
        );
    }


    /**
     * Return posts with a specific tag label in consideration of the selection procedures
     *
     * @param  string  $strTag  The tag name
     *
     * @return Collection|PostModel|Null
     */
    protected function getTaggedPosts($strTag)
    {
        /** @var PageModel $objPage */
        global $objPage;

        // Get posts tags menu settings (archives)
        $moduleTagsMenu = ModuleModel::findById($this->tagmenuModule);

        // Show the posts from particular archive(s)
        if (null === $moduleTagsMenu || empty($varPids = StringUtil::deserialize($moduleTagsMenu->archive))) {
            $objArchives = ArchiveModel::findByPid($objPage->id);

            if (null === $objArchives) {
                return null;
            }

            $varPids = $objArchives->fetchEach('id');
        }

        $objTags = TagsModel::findPublishedByLabelAndArchives($strTag, $varPids);

        if (null === $objTags) {
            return null;
        }

        $varIds = $objTags->fetchEach('pid');

        // Handle featured articles
        if ($this->featured == 'featured_articles') {
            $blnFeatured = true;
        } elseif ($this->featured == 'unfeatured_articles') {
            $blnFeatured = false;
        } else {
            $blnFeatured = null;
        }

        $this->totalPosts = $hardLimit = PostModel::countPublishedByIdsAndFeatured($varIds, $blnFeatured);

        $arrOptions = [];

        // Handle sorting
        if ($this->sortPosts != 'random') {
            $arrOptions['order'] = $this->sortPosts . ' ' . (($this->sortOrder == 'descending') ? 'DESC' : 'ASC');
        }

        // Maximum number of items
        if ($this->numberOfItems > 0) {
            $arrOptions['limit'] = intval($this->numberOfItems);
            $hardLimit = min($arrOptions['limit'], $this->totalPosts);
        }

        // Skip items
        if ($this->skipFirst > 0) {
            $arrOptions['offset'] = intval($this->skipFirst);
        }

        // Items per page
        if ($this->perPage > 0) {
            // Get the current page
            $id = 'page_n' . $this->id;
            $page = Input::get($id) ?? 1;

            // Throw error if the page number is out of range
            if ($page < 1 || $page > max(ceil($hardLimit / $this->perPage), 1)) {
                throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
            }

            $arrOptions['offset'] += (max($page, 1) - 1) * $this->perPage;
            $arrOptions['limit'] = intval($this->perPage);

            // Overall limit
            if ($arrOptions['offset'] + $arrOptions['limit'] >= $hardLimit) {
                $arrOptions['limit'] = $hardLimit - $arrOptions['offset'];
            }
        }

        // Return published articles
        return PostModel::findPublishedByIdsAndFeatured($varIds, $blnFeatured, $arrOptions);
    }


    /**
     * Return posts related to the given post id in consideration of the selection procedures
     *
     * @param  integer  $varId  The post id
     *
     * @return Collection|PostModel|Null
     */
    protected function getRelatedPosts($varId)
    {
        $objPost = PostModel::findPublishedByIdOrAlias($varId);

        if (null === $objPost) {
            return null;
        }

        $varIds = StringUtil::deserialize($objPost->related);

        $this->totalPosts = $hardLimit = PostModel::countPublishedByIds($varIds);
        $arrOptions = [];

        // Handle sorting
        if (!in_array($this->sortRelated, ['random', 'custom'])) {
            $arrOptions['order'] = $this->sortRelated . ' ' . (($this->sortOrder == 'descending') ? 'DESC' : 'ASC');
        }

        // Maximum number of items
        if ($this->numberOfItems > 0) {
            $arrOptions['limit'] = intval($this->numberOfItems);
            $hardLimit = min($arrOptions['limit'], $this->totalPosts);
        }

        // Skip items
        if ($this->skipFirst > 0) {
            $arrOptions['offset'] = intval($this->skipFirst);
        }

        // Items per page
        if ($this->perPage > 0) {
            // Get the current page
            $id = 'page_n' . $this->id;
            $page = Input::get($id) ?? 1;

            // Throw error if the page number is out of range
            if ($page < 1 || $page > max(ceil($hardLimit / $this->perPage), 1)) {
                throw new PageNotFoundException('Page not found: ' . Environment::get('uri'));
            }

            $arrOptions['offset'] += (max($page, 1) - 1) * $this->perPage;
            $arrOptions['limit'] = intval($this->perPage);

            // Overall limit
            if ($arrOptions['offset'] + $arrOptions['limit'] >= $hardLimit) {
                $arrOptions['limit'] = $hardLimit - $arrOptions['offset'];
            }
        }

        // Return published articles
        return PostModel::findPublishedByIds($varIds, $arrOptions);
    }


    /**
     * Renders a post article with teaser and its content
     *
     * @param  PostModel  $objPost
     * @param  boolean  $blnTeaser
     * @param  boolean  $blnContent
     *
     * @return string
     */
    protected function renderPost($objPost, $blnTeaser = false, $blnContent = true)
    {
        /** @var PageModel $objPage */
        global $objPage;

        [$strId, $strClass] = StringUtil::deserialize($objPost->cssID, true);
        [$lat, $long] = StringUtil::deserialize($objPost->latlong);

        if ($strClass != '') {
            $strClass = ' ' . $strClass;
        }
        if ($objPost->featured) {
            $strClass .= ' featured';
        }
        if ($objPost->format != 'standard') {
            $strClass .= ' ' . $objPost->format;
        }

        $objPostTemplate = new FrontendTemplate($this->postTemplate);
        $objPostTemplate->setData($objPost->row());

        // Add html data
        $objPostTemplate->cssId = ($strId) ?: 'teaser-' . $objPost->id;
        $objPostTemplate->cssClass = $strClass;
        $objPostTemplate->href = Posts::generatePostUrl($objPost, $this->alternativeLink);
        $objPostTemplate->attributes = ($objPost->alternativeLink && $objPost->target) ? ' target="_blank"' : '';
        $objPostTemplate->readMore =
            StringUtil::specialchars(sprintf($GLOBALS['TL_LANG']['MSC']['open'], $objPost->url));
        $objPostTemplate->more = $GLOBALS['TL_LANG']['MSC']['more'];

        // Add teaser
        if ($blnTeaser) {
            $objPostTemplate->showTeaser = true;

            // Add meta information
            $objPostTemplate->date = Date::parse($objPage->datimFormat, $objPost->date);
            $objPostTemplate->timestamp = $objPost->date;
            $objPostTemplate->datetime = date('Y-m-d\TH:i:sP', $objPost->date);
            $objPostTemplate->location = $objPost->location;
            $objPostTemplate->latlong = ($lat != '' && $long != '') ? $lat . ', ' . $long : false;

            // Add teaser data
            $objPostTemplate->title = StringUtil::specialchars($objPost->title);
            $objPostTemplate->subtitle = $objPost->subTitle;
            $objPostTemplate->teaser = StringUtil::toHtml5($objPost->teaser);

            // Add author
            if (($objAuthor = $objPost->getRelated('author')) instanceof UserModel) {
                $objPostTemplate->author = $objAuthor->name;
            }

            // Add image
            $objPostTemplate->addImage = false;

            if ($objPost->addImage && $objPost->singleSRC != '') {
                $objModel = FilesModel::findByUuid($objPost->singleSRC);

                if ($objModel !== null && is_file(TL_ROOT . '/' . $objModel->path)) {
                    $this->addImageToTemplate($objPostTemplate,
                        [
                            'singleSRC' => $objModel->path,
                            'size'      => $this->imgSize,
                            'alt'       => $objPost->alt,
                            'title'     => $objPost->title,
                            'caption'   => $objPost->caption,
                        ]
                    );
                }
            }

            if (!$blnContent && class_exists(CommentsModel::class)) {
                // Add comments information
                $intCCount = CommentsModel::countPublishedBySourceAndParent('tl_posts', $objPost->id);

                $objPostTemplate->ccount = $intCCount;
                $objPostTemplate->comments = ($intCCount > 0) ? sprintf($GLOBALS['TL_LANG']['MSC']['commentCount'],
                    $intCCount
                ) : $GLOBALS['TL_LANG']['MSC']['noComments'];
            }
        }

        // Add post content
        if ($blnContent) {
            $objPostTemplate->elements = Posts::getPostContent($objPost);
        } else {
            $objPostTemplate->elements = [];
        }

        return $objPostTemplate->parse();
    }

}
