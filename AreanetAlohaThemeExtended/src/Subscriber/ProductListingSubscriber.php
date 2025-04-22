<?php declare(strict_types=1);

namespace AreanetAlohaThemeExtended\Subscriber;

use Shopware\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductListingResultEvent;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductListingSubscriber implements EventSubscriberInterface
{
    private SalesChannelRepository $salesChannelProductRepository;

    public function __construct(SalesChannelRepository $salesChannelProductRepository)
    {
        $this->salesChannelProductRepository = $salesChannelProductRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductListingCriteriaEvent::class => 'onProductListingCriteria',
            ProductListingResultEvent::class => 'onProductListingResultLoaded'
        ];
    }

    public function onProductListingCriteria(ProductListingCriteriaEvent $event): void
    {
        $criteria = $event->getCriteria();

        $criteria->addAssociation('configuratorSettings');
        $criteria->addAssociation('configuratorSettings.product');
        $criteria->addAssociation('configuratorSettings.option');
        $criteria->addAssociation('configuratorSettings.option.group');

    }

    public function onProductListingResultLoaded(ProductListingResultEvent $event): void
    {
        $context = $event->getSalesChannelContext();
        $result = $event->getResult();

        foreach ($event->getResult() as $product) {
            $alohaConfiguratorOptions = array();
            $displayType = null;

            if($product->getConfiguratorSettings() && count($product->getConfiguratorSettings())){
                foreach($product->getConfiguratorSettings() as $configuratorSettings){
                    $option     = $configuratorSettings->getOption();
                    $cProduct   = $configuratorSettings->getProduct();
                    $group      = $option->getGroup();
                    if($group->getPosition() == 1){
                        $displayType = $group->getDisplayType();
                        $alohaConfiguratorOptions[] = array('product' => $cProduct, 'option' => $option);
                    }
                }
                $product->alohaConfiguratorListing = array('displayType' => $displayType, 'settings' => $alohaConfiguratorOptions);
            }

        }
    }

}
