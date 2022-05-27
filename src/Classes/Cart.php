<?php

/**
 * ontheflyintegration plugin for Craft CMS 3.x
 *
 * ontheflyintegration
 *
 * @link      http://wearesugarrush.co/
 * @copyright Copyright (c) 2022 Sugar Rush
 */

namespace OnTheFlyConfigurator\Classes;

use craft\commerce\elements\Variant;
use craft\commerce\Plugin;
use GuzzleHttp\Client;
use OnTheFlyConfigurator\OnTheFlyIntegration;

class Cart
{
    public function setCartItemsFromOtf($token = false)
    {
        if ($token) {

            $response = $this->getOrderFromOtf($token);

            if (!$response) {
                return false;
            }

            if ($response->getStatusCode() == 200) {

                $tokenData = json_decode($response->getBody());
                $otfProducts = $this->getOtfProductsArray($tokenData);
                $cart = $this->getCart();

                foreach ($otfProducts as $index => $otfProduct) {
                    if ($otfProduct['external_id']) {
                        if ($otfProduct['model'] == "App\\Models\\Configure") {
                            $this->addConfiguredItemToCart($otfProduct, $cart, $index);
                        } else if ($otfProduct['model'] == "App\\Models\\Customise") {
                            $this->addCustomisedItemToCart($otfProduct, $cart, $index);
                        } else if ($otfProduct['model'] == "App\\Models\\Box") {
                            $this->addBoxedItemToCart($otfProduct, $cart, $index);
                        }
                        \Craft::$app->getElements()->saveElement($cart);
                    }
                    \Craft::$app->getResponse()->redirect('/cart');
                }
            }
        }
    }

    public function getCart(){

        $cart = Plugin::getInstance()->getCarts()->getCart();
        \Craft::$app->getElements()->saveElement($cart);

        return $cart;
    }

    public function getOrderFromOtf($token)
    {

        $settings = OnTheFlyIntegration::getInstance()->getSettings();

        if (!$settings) {
            return false;
        }

        return (new Client)->request('GET', 'https://api.ontheflyconfigurator.com/api/' . $settings->subDomain . '/quotes/' . $token, [
            'headers' => [
                'Xco-Api-Key' => $settings->apiKey
            ]
        ]);
    }

    public function getOtfProductsArray($tokenData)
    {
        $otfProducts = [];

        foreach ($tokenData as $data) {
            foreach ($data->items as $key => $item) {
                $otfProducts[$key] = ['external_id' => $item->external_id, 'model' => $item->model, 'price' => $item->price];
                foreach ($item->variants as $variant) {
                    $otfProducts[$key]['variants'][] = ['external_id' => $variant->external_id, 'price' => $variant->price, 'overwrite_price' => $variant->overwrite_price];
                }
            }
        }

        return $otfProducts;
    }

    public function addConfiguredItemToCart($otfProduct, $cart, $index)
    {
        $product = $this->getVariantBySku($otfProduct['external_id']);

        if ($product) {

            $productPurchasableId = $product->getId();

            $newLineItem = Plugin::getInstance()->getLineItems()->createLineItem($cart->id, $productPurchasableId, []);
            $newLineItem->price = $otfProduct['price'];
            $newLineItem->salePrice = $otfProduct['price'];
            $newLineItem->setOptions(['otfItem' => $index]);

            foreach ($otfProduct['variants'] as $otfVariant) {

                $variant = $this->getVariantBySku($otfVariant['external_id']);

                if (!$variant) {
                    return $otfVariant['external_id'];
                }


                $newLineItem->note = $this->setNote($newLineItem, $otfVariant);
                $newLineItem->price = $this->setPrice($otfVariant, $newLineItem);
                $newLineItem->salePrice = $this->setSalePrice($otfVariant, $newLineItem);
            }

            Plugin::getInstance()->getCarts()->getCart()->setRecalculationMode('none');
            $cart->addLineItem($newLineItem);
        }
    }

    public function addCustomisedItemToCart($otfProduct, $cart, $index)
    {
        $product = $this->getVariantBySku($otfProduct['external_id']);

        if ($product) {
            $productPurchasableId = $product->getId();

            $newLineItem = Plugin::getInstance()->getLineItems()->createLineItem($cart->id, $productPurchasableId, []);
            $newLineItem->price = '';
            $newLineItem->salePrice = '';
            $newLineItem->setOptions(['otfItem' => $index]);

            foreach ($otfProduct['variants'] as $otfVariant) {

                $variant = $this->getVariantBySku($otfVariant['external_id']);

                $price = $otfVariant['overwrite_price'] != "0.00" ? $newLineItem->price + $otfVariant['overwrite_price'] : $newLineItem->price + $otfVariant['price'];

                if (!$variant) {
                    return $otfVariant['external_id'];
                }

                $newLineItem->note = $this->setNote($newLineItem, $otfVariant);
                $newLineItem->price =  $price;
                $newLineItem->salePrice =  $price;
            }


            Plugin::getInstance()->getCarts()->getCart()->setRecalculationMode('none');
            $cart->addLineItem($newLineItem);
        }
    }

    public function addBoxedItemToCart($otfProduct, $cart, $index)
    {
        $product = $this->getVariantBySku($otfProduct['external_id']);

        if ($product) {
            $productPurchasableId = $product->getId();

            $newLineItem = Plugin::getInstance()->getLineItems()->createLineItem($cart->id, $productPurchasableId, []);
            $newLineItem->price = $otfProduct['price'];
            $newLineItem->salePrice = $otfProduct['price'];
            $newLineItem->setOptions(['otfItem' => $index]);

            foreach ($otfProduct['variants'] as $otfVariant) {

                $variant = $this->getVariantBySku($otfVariant['external_id']);

                if (!$variant) {
                    return $otfVariant['external_id'];
                }

                $newLineItem->note = $this->setNote($newLineItem, $otfVariant);;
                $newLineItem->price = $this->setPrice($newLineItem, $otfVariant);
                $newLineItem->salePrice = $this->setSalePrice($newLineItem, $otfVariant);
            }

            Plugin::getInstance()->getCarts()->getCart()->setRecalculationMode('none');
            $cart->addLineItem($newLineItem);
        }
    }

    public function getVariantBySku($sku)
    {
        return Variant::find()->sku($sku)->one();
    }

    public function setNote($newLineItem, $otfVariant){
        return $newLineItem->note . " " . $otfVariant['external_id'] . "\n\r";
    }

    public function setPrice($otfVariant, $newLineItem){
        return $otfVariant['overwrite_price'] != '0.00' ? $newLineItem->price + $otfVariant['overwrite_price'] : $newLineItem->price + 0;
    }

    public function setSalePrice($otfVariant, $newLineItem){
        return $otfVariant['overwrite_price'] != '0.00' ? $newLineItem->price + $otfVariant['overwrite_price'] : $newLineItem->price + 0;
    }
}
