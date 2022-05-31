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
    protected $response;
    protected $token_data;
    protected $otf_products = [];
    protected $cart;

    public function setCartItemsFromOtf($token = false)
    {
        if(!$token) {
            return false;
        }

        $this->getOrderFromOtf($token);

        if(!$this->response) {
            return false;
        }

        if($this->response->getStatusCode() == 200) {
            $this->getTokenData();
            $this->getOtfProductsArray();
            $this->getCart();
            $this->addItemsToCart();
        }
    }

    public function getOrderFromOtf($token)
    {
        $settings = OnTheFlyIntegration::getInstance()->getSettings();

        if(!$settings) {
            return false;
        }

        $this->response = (new Client)->request('GET', 'https://api.ontheflyconfigurator.com/api/' . $settings->subDomain . '/quotes/' . $token, [
            'headers' => [
                'Xco-Api-Key' => $settings->apiKey
            ]
        ]);
    }

    public function getTokenData()
    {
        $this->token_data = json_decode($this->response->getBody());
    }

    public function getOtfProductsArray()
    {
        // Use the key to index options in the cart
        foreach($this->token_data as $data) {
            foreach($data->items as $key => $item) {
                $this->otf_products[$key] = ['external_id' => $item->external_id, 'model' => $item->model, 'price' => $item->price];
                foreach($item->variants as $variant) {
                    $this->otf_products[$key]['variants'][] = ['external_id' => $variant->external_id, 'price' => $variant->price, 'overwrite_price' => $variant->overwrite_price];
                }
            }
        }
    }

    public function getCart()
    {
        $this->cart = Plugin::getInstance()->getCarts()->getCart();
        \Craft::$app->getElements()->saveElement($this->cart);
    }

    public function addItemsToCart()
    {
        foreach($this->otf_products as $index => $otfProduct) {
            if($otfProduct['external_id']) {
                if($otfProduct['model'] == "App\\Models\\Configure") {
                    $this->addConfiguredItemToCart($otfProduct, $index);
                } else if($otfProduct['model'] == "App\\Models\\Customise") {
                    $this->addCustomisedItemToCart($otfProduct, $index);
                } else if($otfProduct['model'] == "App\\Models\\Box") {
                    $this->addBoxedItemToCart($otfProduct, $index);
                }
                \Craft::$app->getElements()->saveElement($this->cart);
            }
            \Craft::$app->getResponse()->redirect('/cart');
        }
    }

    public function addConfiguredItemToCart($otfProduct, $index)
    {
        $product = $this->getVariantBySku($otfProduct['external_id']);

        if($product) {

            $newLineItem = Plugin::getInstance()->getLineItems()->createLineItem($this->cart->id, $product->getId(), []);
            $newLineItem->price = $otfProduct['price'];
            $newLineItem->salePrice = $otfProduct['price'];

            // Creates unique cart items for each product
            $newLineItem->setOptions(['otfItem' => $index]);

            foreach($otfProduct['variants'] as $otfVariant) {

                $variant = $this->getVariantBySku($otfVariant['external_id']);

                if(!$variant) {
                    return $otfVariant['external_id'];
                }

                $newLineItem->note = $this->setNote($newLineItem, $otfVariant);
                $newLineItem->price = $this->setPrice($otfVariant, $newLineItem);
                $newLineItem->salePrice = $this->setSalePrice($otfVariant, $newLineItem);
            }

            $this->addItemToCart($newLineItem);
        }
    }

    public function getVariantBySku($sku)
    {
        return Variant::find()->sku($sku)->one();
    }

    public function setNote($newLineItem, $otfVariant)
    {
        return $newLineItem->note . " " . $otfVariant['external_id'] . "\n\r";
    }

    public function setPrice($otfVariant, $newLineItem)
    {
        return $otfVariant['overwrite_price'] != '0.00' ? $newLineItem->price + $otfVariant['overwrite_price'] : $newLineItem->price + 0;
    }

    public function setSalePrice($otfVariant, $newLineItem)
    {
        return $otfVariant['overwrite_price'] != '0.00' ? $newLineItem->price + $otfVariant['overwrite_price'] : $newLineItem->price + 0;
    }

    public function addItemToCart($lineItem)
    {
        Plugin::getInstance()->getCarts()->getCart()->setRecalculationMode('none');
        $this->cart->addLineItem($lineItem);
    }

    public function addCustomisedItemToCart($otfProduct, $index)
    {
        $product = $this->getVariantBySku($otfProduct['external_id']);

        if($product) {

            $newLineItem = Plugin::getInstance()->getLineItems()->createLineItem($this->cart->id, $product->getId(), []);

            // Creates unique cart items for each product
            $newLineItem->setOptions(['otfItem' => $index]);

            foreach($otfProduct['variants'] as $otfVariant) {

                $variant = $this->getVariantBySku($otfVariant['external_id']);

                $price = $otfVariant['overwrite_price'] != "0.00" ? $newLineItem->price + $otfVariant['overwrite_price'] : $newLineItem->price + $otfVariant['price'];

                if(!$variant) {
                    return $otfVariant['external_id'];
                }

                $newLineItem->note = $this->setNote($newLineItem, $otfVariant);
                $newLineItem->price = $price;
                $newLineItem->salePrice = $price;
            }

            $this->addItemToCart($newLineItem);
        }
    }

    public function addBoxedItemToCart($otfProduct, $index)
    {
        $product = $this->getVariantBySku($otfProduct['external_id']);

        if($product) {

            $newLineItem = Plugin::getInstance()->getLineItems()->createLineItem($this->cart->id, $product->getId(), []);
            $newLineItem->price = $otfProduct['price'];
            $newLineItem->salePrice = $otfProduct['price'];

            // Creates unique cart items for each product
            $newLineItem->setOptions(['otfItem' => $index]);

            foreach($otfProduct['variants'] as $otfVariant) {

                $variant = $this->getVariantBySku($otfVariant['external_id']);

                if(!$variant) {
                    return $otfVariant['external_id'];
                }

                $newLineItem->note = $this->setNote($newLineItem, $otfVariant);;
                $newLineItem->price = $this->setPrice($otfVariant, $newLineItem);
                $newLineItem->salePrice = $this->setSalePrice($otfVariant, $newLineItem);
            }

            $this->addItemToCart($newLineItem);
        }
    }
}
