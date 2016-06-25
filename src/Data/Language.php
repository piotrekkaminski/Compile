<?php
namespace M2t\Data;

class Language
{
    public static $languageCodes = [
        "all",
        "ar_SA",
        "ca_ES",
        "cs_CZ",
        "da_DK",
        "de_DE",
        "el_GR",
        "en_PT",
        "en_US",
        "es_AR",
        "es_ES",
        "et_EE",
        "fa_IR",
        "fi_FI",
        "fr_FR",
        "he_IL",
        "hr_HR",
        "hu_HU",
        "it_IT",
        "ja_JP",
        "lv_LV",
        "nl_NL",
        "pl_PL",
        "pt_BR",
        "pt_PT",
        "ru_RU",
        "sk_SK",
        "tr_TR",
        "uk_UA",
        "vi_VN",
        "zh_CN",
        "zh_TW",
    ];

    public static function languageCodes()
    {
        return self::$languageCodes;
    }
}