<?php

namespace MakinaCorpus\Drupal\Layout\Render;

use MakinaCorpus\Layout\Controller\Context;
use MakinaCorpus\Layout\Grid\ColumnContainer;
use MakinaCorpus\Layout\Grid\ContainerInterface;
use MakinaCorpus\Layout\Grid\HorizontalContainer;
use MakinaCorpus\Layout\Grid\ItemInterface;
use MakinaCorpus\Layout\Grid\TopLevelContainer;
use MakinaCorpus\Layout\Render\BootstrapGridRenderer;
use MakinaCorpus\Layout\Render\RenderCollection;

/**
 * Bootstrap 3 compatible grid renderer.
 */
class BootstrapRendererDecorator extends BootstrapGridRenderer
{
    /**
     * @var Context
     */
    private $context;

    /**
     * Default constructor
     *
     * @param Context $context
     */
    public function __construct(Context $context)
    {
        $this->context = $context;
    }

    /**
     * Render a single child
     *
     * @param ItemInterface $item
     * @param RenderCollection $collection
     * @param ContainerInterface $parent
     *
     * @return string
     */
    protected function doRenderChild(ItemInterface $item, RenderCollection $collection, ContainerInterface $parent = null) : string
    {
        $rendered = $collection->getRenderedItem($item, false);

        if (!$this->context->hasToken()) {
            return $rendered;
        }

        if (!$rendered) {
            $rendered = '<p class="text-danger">' . t("Broken or missing item") . '</span>';
        }

        $identifier = $collection->identify($item);

        if (!$item instanceof ContainerInterface) {
            $rendered = '<div data-id="' . $identifier . '" data-item>' . $this->renderMenu($item, $this->getItemButtons($item, $parent)) . $rendered . '</div>';
        }

        return $rendered;
    }

    /**
     * {@inheritdoc}
     */
    public function renderTopLevelContainer(TopLevelContainer $container, RenderCollection $collection) : string
    {
        if ($this->context->hasToken()) {
            $innerText = $this->renderMenu($container, $this->getTopLevelContainerButtons($container));
        } else {
            $innerText = '';
        }

        foreach ($container->getAllItems() as $child) {
            $innerText .= $this->doRenderChild($child, $collection, $container);
        }

        return $this->doRenderTopLevelContainer($container, $innerText, $collection->identify($container));
    }

    /**
     * {@inheritdoc}
     */
    public function renderColumnContainer(ColumnContainer $container, RenderCollection $collection) : string
    {
        if ($this->context->hasToken()) {
            $innerText = $this->renderMenu($container, $this->getColumnButtons($container));
        } else {
            $innerText = '';
        }

        foreach ($container->getAllItems() as $child) {
            $innerText .= $this->doRenderChild($child, $collection, $container);
        }

        return $innerText;
    }

    /**
     * {@inheritdoc}
     */
    public function renderHorizontalContainer(HorizontalContainer $container, RenderCollection $collection) : string
    {
        // Do not display container options if they are children because
        // they will be merge to each child menu instead
        if ($this->context->hasToken()) {
            $innerText = $this->renderMenu($container, $this->getHorizontalButtons($container));
        } else {
            $innerText = '';
        }

        if (!$container->isEmpty()) {
            $innerContainers = $container->getAllItems();
            $defaultSize = floor(12 / count($innerContainers));

            foreach ($innerContainers as $child) {
                $innerText .= $this->doRenderColumn(
                    $child,
                    ['md' => $defaultSize],
                    $collection->getRenderedItem($child),
                    $collection->identify($child)
                );
            }
        }

        return $this->doRenderHorizontalContainer($container, $innerText, $collection->identify($container));
    }

    private function renderMenu(ItemInterface $item, array $links) : string
    {
        if ($item instanceof ColumnContainer) {
            $title = t("Column");
        } else if ($item instanceof HorizontalContainer) {
            $title = t("Columns container");
        } else if ($item instanceof TopLevelContainer) {
            $title = t("Top level container");
        } else {
            $title = t("Item");
        }
        $links = '<li>' . implode('</li><li>', $links) . '</li>';

        return <<<EOT
<div class="layout-menu">
  <a href="#" title="{$title}">
    <span class="glyphicon glyphicon-cog" aria-hidden="true"></span>
    <span class="title">{$title}</span>
  </a>
  <ul>
    {$links}
  </ul>
</div>
EOT;
    }

    private function renderLink($title, $route, array $parameters, string $icon = null) : string
    {
        foreach ($parameters as $name => $value) {
            $search = '{' . $name . '}';
            if (false !== strpos($route, $search)) {
                $route = str_replace($search, $value, $route);
                unset($parameters[$name]);
            }
        }

        if ($icon) {
            $title = '<span class="glyphicon glyphicon-' . $icon . '" aria-hidden="true"></span> ' . $title;
        }

        return l($title, $route, ['query' => $parameters, 'html' => true]);
    }

    private function createOptions(ItemInterface $item, array $options) : array
    {
        return array_merge(drupal_get_destination(), [
            'tokenString' => $this->context->getCurrentToken()->getToken(),
            'layoutId' => $item->getLayoutId(),
        ], $options);
    }

    private function getColumnButtons(ColumnContainer $container) : array
    {
        $parent   = $container->getParent();
        $parentId = $parent->getStorageId();
        $index    = $parent->getIndexOf($container);

        // Merge with parent options, visually it's better to hide the parent
        // menu and use its children to replicate its context
        return [
            $this->renderLink(
                t('Prepend column container'),
                'layout/ajax/add-column-container',
                $this->createOptions($container, [
                    'containerId' => $container->getStorageId(),
                    'position' => 0,
                    'columnCount' => 2,
                ]),
                'th-large'
            ),
            $this->renderLink(
                t('Append column container'),
                'layout/ajax/add-column-container',
                $this->createOptions($container, [
                    'containerId' => $container->getStorageId(),
                    'position' => $container->count(),
                    'columnCount' => 2,
                ]),
                'th-large'
            ),
            '<li class="divider"></li>',
            $this->renderLink(
                t("Prepend item"),
                'layout/callback/add-item',
                $this->createOptions($container, [
                    'containerId' => $container->getStorageId(),
                    'position' => 0,
                ]),
                'picture'
            ),
            $this->renderLink(
                t("Append item"),
                'layout/callback/add-item',
                $this->createOptions($container, [
                    'containerId' => $container->getStorageId(),
                    'position' => $container->count(),
                ]),
                'picture'
            ),
            '<li class="divider"></li>',
            $this->renderLink(
                t('Add column before'),
                'layout/ajax/add-column',
                $this->createOptions($container, [
                    'containerId' => $parentId,
                    'position' => $index,
                ]),
                'chevron-left'
            ),
            $this->renderLink(
                t('Add column after'),
                'layout/ajax/add-column',
                $this->createOptions($container, [
                    'containerId' => $parentId,
                    'position' => $index + 1,
                ]),
                'chevron-right'
            ),
            $this->renderLink(
                t('Remove this column'),
                'layout/ajax/remove-column',
                $this->createOptions($container, [
                    'containerId' => $parentId,
                    'position' => $index,
                ]),
                'remove'
            ),
        ];
    }

    private function getHorizontalButtons(HorizontalContainer $container) : array
    {
        return [
            $this->renderLink(
                t('Prepend column'),
                'layout/ajax/add-column',
                $this->createOptions($container, [
                    'containerId' => $container->getStorageId(),
                    'position' => 0,
                ]),
                'chevron-left'
            ),
            $this->renderLink(
                t('Append column'),
                'layout/ajax/add-column',
                $this->createOptions($container, [
                    'containerId' => $container->getStorageId(),
                    'position' => $container->count(),
                ]),
                'chevron-right'
            ),
            $this->renderLink(
                t('Remove'),
                'layout/ajax/remove',
                $this->createOptions($container, [
                    'itemId' => $container->getStorageId(),
                ]),
                'remove'
            ),
        ];
    }

    private function getTopLevelContainerButtons(TopLevelContainer $container) : array
    {
        return [
            $this->renderLink(
                t('Prepend column container'),
                'layout/ajax/add-column-container',
                $this->createOptions($container, [
                    'containerId' => $container->getStorageId(),
                    'position' => 0,
                    'columnCount' => 2,
                ]),
                'th-large'
            ),
            $this->renderLink(
                t('Append column container'),
                'layout/ajax/add-column-container',
                $this->createOptions($container, [
                    'containerId' => $container->getStorageId(),
                    'position' => $container->count(),
                    'columnCount' => 2,
                ]),
                'th-large'
            ),
            '<li class="divider"></li>',
            $this->renderLink(
                t("Prepend item"),
                'layout/callback/add-item',
                $this->createOptions($container, [
                    'containerId' => $container->getStorageId(),
                    'position' => 0,
                ]),
                'picture'
            ),
            $this->renderLink(
                t("Append item"),
                'layout/callback/add-item',
                $this->createOptions($container, [
                    'containerId' => $container->getStorageId(),
                    'position' => $container->count(),
                ]),
                'picture'
            ),
        ];
    }

    private function getItemButtons(ItemInterface $item, ContainerInterface $parent) : array
    {
        return [
            $this->renderLink(
                t('Move to top'),
                'layout/ajax/move',
                $this->createOptions($item, [
                    'itemId' => $item->getStorageId(),
                    'containerId' => $parent->getStorageId(),
                    'newPosition' => 0,
                ]),
                'chevron-up'
            ),
            $this->renderLink(
                t('Move to bottom'),
                'layout/ajax/move',
                $this->createOptions($item, [
                    'itemId' => $item->getStorageId(),
                    'containerId' => $parent->getStorageId(),
                    'newPosition' => $parent->count(),
                ]),
                'chevron-down'
            ),
            $this->renderLink(
                t('Options'),
                'layout/callback/edit-item',
                $this->createOptions($item, [
                    'itemId' => $item->getStorageId(),
                ]),
                'cog'
            ),
            $this->renderLink(
                t('Remove'),
                'layout/ajax/remove',
                $this->createOptions($item, [
                    'itemId' => $item->getStorageId(),
                ]),
                'remove'
            ),
        ];
    }
}
