<?php

declare(strict_types=1);

namespace Prostor\CumDiscount\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray;

class Thresholds extends AbstractFieldArray
{
    protected function _prepareToRender(): void
    {
        $this->addColumn(
            'minimum_spend',
            ['label' => __('Minimum spend'), 'class' => 'required-entry validate-number']
        );
        $this->addColumn(
            'percentage',
            ['label' => __('Discount percentage'), 'class' => 'required-entry validate-number']
        );
        $this->_addAfter = false;
        $this->_addButtonLabel = __('Add Threshold');
    }
}
