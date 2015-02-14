<?php

mtoClass :: import("mtokit/imagekit/mtoAbstractImageFilter.class.php");


class mtoImVectorMergeImageFilter extends mtoAbstractImageFilter
{

  function apply(mtoAbstractImageContainer $container)
  {
    $width = $container->getWidth();
    $height = $container->getHeight();

    $layers = $this->getParam('layers');
    
    $container->getResource()->setImageMatte( true );
    $it = $container->getResource()->getPixelIterator();
    foreach( $it as $row => $pixels )
    {
            foreach ( $pixels as $column => $pixel )
            {
                //if(!$pixel->isSimilar($color_pix,1))

                if ($pixel->getColorValue (imagick::COLOR_ALPHA) != 0)
                {
                    $op = $pixel->getColorValue(imagick::COLOR_OPACITY);
                    $pixel->setColor($this->getParam('base_color'));
                    $pixel->setColorValue(imagick::COLOR_OPACITY,$op);
                    
                }

                //var_dump();

            }

        $it->syncIterator();
     }


    foreach($layers as $layer)
    {
        
        $wm_cont = new Imagick();
        $wm_cont->readImage($layer['file']);
        $wm_cont ->setImageMatte( true );
        //$color_pix = new ImagickPixel('transparent');
        $it = $wm_cont->getPixelIterator();
        foreach( $it as $row => $pixels )
        {
            foreach ( $pixels as $column => $pixel )
            {
                //if(!$pixel->isSimilar($color_pix,1))
                
                if ($pixel->getColorValue (imagick::COLOR_ALPHA) != 0)
                {
                    $op = $pixel->getColorValue(imagick::COLOR_OPACITY);
                    $pixel->setColor($layer['color']);
                    $pixel->setColorValue(imagick::COLOR_OPACITY,$op);
                } else
                {
                    //var_dump($pixel->getColorValue (imagick::COLOR_ALPHA));
                }    
                
                //var_dump();

        }
        $it->syncIterator();
        }
        //var_dump($wm_cont->getImageColors());
        $container->getResource()->compositeImage($wm_cont, Imagick::COMPOSITE_OVER, 0, 0, Imagick::CHANNEL_ALL);
     }
   
  
     $container->getResource()->enhanceImage();
  }

  
}
