<?php

namespace App\Services;

class StringSimilarity {
    public static function compare($str_a, $str_b) {
        $length = strlen($str_a);
        $length_b = strlen($str_b);

        $i = 0;
        $segmentcount = 0;
        $segmentsinfo = array();
        $segment = '';
        while ($i < $length) 
        {
            $char = substr($str_a, $i, 1);
            if (strpos($str_b, $char) !== FALSE) 
            {               
                $segment = $segment.$char;
                if (strpos($str_b, $segment) !== FALSE) 
                {
                    $segmentpos_a = $i - strlen($segment) + 1;
                    $segmentpos_b = strpos($str_b, $segment);
                    $positiondiff = abs($segmentpos_a - $segmentpos_b);
                    $posfactor = ($length - $positiondiff) / $length_b; // <-- ?
                    $lengthfactor = strlen($segment)/$length;
                    $segmentsinfo[$segmentcount] = array( 'segment' => $segment, 'score' => ($posfactor * $lengthfactor));
                } 
                else 
                {
                    $segment = '';
                    $i--;
                    $segmentcount++;
                } 
            } 
            else 
            {
                $segment = '';
                $segmentcount++;
            }
            $i++;
        }   

        // PHP 5.3 lambda in array_map      
        $totalscore = array_sum(array_map(function($v) { return $v['score'];  }, $segmentsinfo));
        return $totalscore;     
    }
}