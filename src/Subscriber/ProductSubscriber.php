<?php declare(strict_types=1);

namespace AreanetAlohaThemeExtended\Subscriber;

use Shopware\Core\Content\Product\AbstractIsNewDetector;
use Shopware\Core\Content\Product\AbstractProductMaxPurchaseCalculator;
use Shopware\Core\Content\Product\AbstractProductVariationBuilder;
use Shopware\Core\Content\Product\AbstractPropertyGroupSorter;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceContainer;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\Price\AbstractProductPriceCalculator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
class ProductSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractProductVariationBuilder      $productVariationBuilder,
        private readonly AbstractProductPriceCalculator       $calculator,
        private readonly AbstractPropertyGroupSorter          $propertyGroupSorter,
        private readonly SystemConfigService                  $systemConfigService
    )
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'sales_channel.' . ProductEvents::PRODUCT_LOADED_EVENT => 'salesChannelLoaded',
            'sales_channel.product.partial_loaded' => 'salesChannelLoaded',
        ];
    }

    public function salesChannelLoaded(SalesChannelEntityLoadedEvent $event): void
    {

        $salesChannelContext    = $event->getSalesChannelContext();
        $isEnabled              = $this->systemConfigService->get('AreanetAlohaThemeExtended.config.mergeOptionsAndProperties', $salesChannelContext->getSalesChannelId());

        if (!$isEnabled) {
            return;
        }

        foreach ($event->getEntities() as $product) {

            $assigns = [];

            $properties = $product->get('properties') ? $product->get('properties') : null;
            $options    = $product->get('properties') ? $product->get('options') : null;
            if ($properties) {
                if($options) $properties->merge($options);
                $assigns['sortedProperties'] = $this->propertyGroupSorter->sort($properties);
            }elseif ($options){
                $assigns['sortedProperties'] = $this->propertyGroupSorter->sort($options);
            }

            $product->assign($assigns);

            $this->productVariationBuilder->build($product);
        }

        $this->calculator->calculate($event->getEntities(), $event->getSalesChannelContext());
    }

}
