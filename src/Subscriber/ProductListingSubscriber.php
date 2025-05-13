<?php declare(strict_types=1);

namespace AreanetAlohaThemeExtended\Subscriber;


use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\Events\ProductSearchResultEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestResultEvent;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
class ProductListingSubscriber implements EventSubscriberInterface
{
    public const EXTENSION_NAME = 'alohaVariants';

    /**
     * @internal
     */
    public function __construct(private EntityRepository $productRepository)
    {

    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingResultEvent::class => 'loadVariants',
            ProductSearchResultEvent::class => 'loadVariants',
            ProductSuggestResultEvent::class => 'loadVariants',
        ];
    }

    public function loadVariants(ProductListingResultEvent $event): void
    {
        $result = $event->getResult();

        $variantProducts = $result->filter(function(ProductEntity $entity) {
            return (int) $entity->getChildCount() > 0 || $entity->getParentId() !== null;
        });

        $parentIds = [];
        foreach ($variantProducts as $variantProduct) {
            $parentIds[] = $variantProduct->getParentId() ?? $variantProduct->getId();
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('parentId', $parentIds))
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('visibilities.salesChannelId', $event->getSalesChannelContext()->getSalesChannelId()))
            ->addAssociations(['options', 'options.group', 'options.media', 'cover', 'visibilities']);

        $products = $this->productRepository->search($criteria, $event->getContext());
        $colorVariants = [];
        $stocks = [];
        // Maybe SEO URL is an option for a quick solution
        foreach ($products as $product) {
            foreach ($product->getOptions() as $option) {
                $groupCustomFields = $option->getGroup()->getCustomFields();
                if(!empty($groupCustomFields['aloha_theme_option_group_show_in_listing'])) {
                    if (isset($colorVariants[$product->getParentId()][$option->getName()])) {
                        $colorVariants[$product->getParentId()][$option->getName()]['stock'] = max(
                            $colorVariants[$product->getParentId()][$option->getName()]['stock'],
                            $product->getStock()
                        );
                    } else {
                        $cover = $product->getCover() ? $product->getCover()->getMedia() : null;
                        $colorVariants[$product->getParentId()][$option->getName()] = [
                            'colorCode' => $option->getColorHexCode(),
                            'productId' => $product->getId(),
                            'image' => $option->getMedia() ? $option->getMedia() : $cover,
                            'stock' => $product->getStock(),
                            'position' => $option->getPosition(),
                            'name'=> $option->getName()
                        ];
                    }
                    $stocks[$product->getParentId()] = max(
                        $stocks[$product->getParentId()]??0,
                        $product->getStock()
                    );
                }
            }
        }

        foreach ($result->getEntities() as $listingProduct) {
            $parentId = $listingProduct->getParentId() ?? $listingProduct->getId();
            if (isset($colorVariants[$parentId])) {
                uasort($colorVariants[$parentId], function($a, $b) {
                    if (isset($a['position']) && isset($b['position'])) {
                        return ($a['position'] <= $b['position']) ? -1 : 1;
                    }
                });

                $listingProduct->addExtension(self::EXTENSION_NAME, new ArrayEntity(['variants' => $colorVariants[$parentId], 'available' => $stocks[$parentId] > 0]));
            }
        }
    }

}
