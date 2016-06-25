<?php
\Magento\Framework\Component\ComponentRegistrar::register(
    \Magento\Framework\Component\ComponentRegistrar::LANGUAGE,
    'magentotwotranslations_{{ language|lower }}',
    __DIR__
);