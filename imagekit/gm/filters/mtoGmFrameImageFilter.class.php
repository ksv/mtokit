<?php

mtoClass :: import("mtokit/imagekit/mtoAbstractImageFilter.class.php");

class mtoGmFrameImageFilter extends mtoAbstractImageFilter
{

    function apply(mtoAbstractImageContainer $container)
    {
        $w = $container->getWidth();
        $h = $container->getHeight();
        $color = "#" . str_replace(array(":", "#"), "", $this->getParam('color'));
        $fx = $this->getParam('x');
        $fy = $this->getParam('y');
        $fw = $this->getParam('width');
        $fh = $this->getParam('height');
        $ftw = $this->getParam('all_width', 100);
        $fth = $this->getParam('all_height', 100);
        $dashed = ($this->getParam('dashed') ? $this->getParam('dashed') : 0);
        $filled = ($this->getParam('filled') ? $this->getParam('filled') : 0);



        $x1 = (int) ($w * $fx / $ftw);
        $y1 = (int) ($h * $fy / $fth);
        $x2 = (int) ($x1 + $w * $fw / $ftw);
        $y2 = (int) ($y1 + $h * $fh / $fth);
        if ($x2 == $w)
            $x2--;
        if ($y2 == $h)
            $y2--;
        $draw = new GmagickDraw();
        $draw->setStrokeColor(new GmagickPixel($color));
        if ($dashed)
        {
            $draw->setStrokeDashArray(array(5, 3, 2));
        }
        if ($filled)
        {
            $draw->setFillColor(new GmagickPixel($color));
        }
        else
        {
            $draw->setFillColor(new GmagickPixel("transparent"));
        }
        $draw->rectangle($x1, $y1, $x2, $y2);
        $container->getResource()->drawImage($draw);
        $container->getResource()->enhanceImage();
    }




}
