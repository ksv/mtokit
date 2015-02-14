<?php

mtoClass :: import("mtokit/imagekit/mtoAbstractImageFilter.class.php");

class mtoImLineImageFilter extends mtoAbstractImageFilter
{

    function apply(mtoAbstractImageContainer $container)
    {
        $w = $container->getWidth();
        $h = $container->getHeight();
        $color = "#".str_replace(array(":","#"), "", $this->getParam('color'));
        $start_x = (int)$this->getParam('st_x');
        $start_y = (int)$this->getParam('st_y');
        $end_x = (int)$this->getParam('end_x');
        $end_y = (int)$this->getParam('end_y');                
        $ftw = $this->getParam('all_width');
        $fth = $this->getParam('all_height');
        $dashed = ($this->getParam('dashed')?$this->getParam('dashed'):0);
        //$filled = ($this->getParam('filled')?$this->getParam('filled'):0);
        $draw = new ImagickDraw();       
        $draw->setFillColor(new ImagickPixel("transparent"));
        $draw->setFillOpacity(0);
        if ($dashed)
        {
            $draw->setStrokeColor(new ImagickPixel($color));
            $draw->setStrokeDashArray(array(5,5,5));
        } else
        {
            $draw->setFillColor(new ImagickPixel($color));
        }    
        
        
        $draw -> line($start_x, $start_y, $end_x, $end_y);
        $container->getResource()->drawImage($draw);
        $container->getResource()->enhanceImage();
    }




}
