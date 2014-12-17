<?php
namespace Ens\JobeetBundle\Utils;

class Jobeet
{
    static public function slugify($text)
    {
        $text = preg_replace('#[^\\pL\d]+#u', '-', $text);
        $text = trim($text, '-');

        if (function_exists('iconv'))
        {
            $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        }

        $text = strtolower($text);
        $text = preg_replace('#[^-\w]+#', '', $text);

        if (empty(preg_replace('/\s+/', '', $text))) {
            return 'n-a';
        }

        return $text;
    }
}
?>