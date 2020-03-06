<?php
/**
 * gen-composite-image:
 *  - создание изображения размером IMG_WIDTH х IMG_HEIGHT, 
 *  - вставка вниз другого изображения (с масштабированием)
 *  - умещение текста (название файла) вверху
 * 
 * Исходные изображения берутся из директории с запускаемым скриптом,
 *  итоговые сохраняются в в аналогичную директорию с постфиксом _2
 * 
 * Изображения должны быть с IMG_EX расширением  
 * 
 * PHP Version 7.1
 * 
 * @author Buturlin Vitaliy (Byurrer), email: byurrer@mail.ru
 * @copyright 2020 Buturlin Vitaliy
 * @license MIT https://opensource.org/licenses/mit-license.php
 */

 //#########################################################################

header("Content-type: text/plain; charset: utf8;");

//! путь до файла шрифта
define("FONT_FILE", dirname(__FILE__) . '/arial.ttf');


//! размер шрифта
define("FONT_SIZE", 82);

//! коэффициент уменьшения размера шрифта (при вмещении в область)
define("FONT_SCALE", 0.9);


//! ширина итогового изображения
define("IMG_WIDTH", 1500);

//! высота итогового изображения
define("IMG_HEIGHT", 1000);

//! расширение изображений
define("IMG_EX", "jpg");


//! ширина текстовой области
define("TEXT_WIDTH", IMG_WIDTH-200);

//! высота текстовой области
define("TEXT_HEIGHT", 180);

//**************************************************************************

//откуда брать изображения
$sSrcDir = __DIR__;

//куда складывать итоговые изображения
$sDestDir = __DIR__."_2";

if(!file_exists($sDestDir))
  mkdir($sDestDir);

//##########################################################################

//! сканируем директорию, на выходе получаем все пути до файлов (в том числе и вложенных)
function ScanPath($dir)
{
  $d = array();
  $arr = opendir($dir);
   
  while($v = readdir($arr))
  {
    if($v == '.' or $v == '..') continue;
    if(!is_dir($dir.DIRECTORY_SEPARATOR.$v)) 
			$d[] = $v;
			
    if(is_dir($dir.DIRECTORY_SEPARATOR.$v)) 
    {
      $aArr = ScanPath($dir.DIRECTORY_SEPARATOR.$v);
                      
      for($i=0, $il=count($aArr); $i<$il; ++$i)
      {
        $d[] = $v.DIRECTORY_SEPARATOR.$aArr[$i];
      }
    }
  }
   
  return $d;
}
  
//! загрузка изображения, возвращает resource
function LoadImg($sPath)
{
  return imagecreatefromstring(file_get_contents($sPath));
}

//! возвращает расширение файла
function GetExtension($sFile) 
{
  return substr(strrchr($sFile, '.'), 1);
}

//! вместить текст sText размером iSize (может уменьшаться функцией) в размер iWidth и iHeight
function ContainText($sText, $iWidth, $iHeight, &$iSize, $sFont)
{
	$aText = explode(" ", $sText);
	$sStr = $sText;
  
  while(1)
  {
    $aBox = imagettfbbox($iSize, 0, $sFont, $sStr);

    if($aBox[2] > $iWidth || $aBox[3] > $iHeight)
    {
      $iSize *= FONT_SCALE;

      $aBox = imagettfbbox($iSize, 0, $sFont, $sStr);

      if($aBox[2] > $iWidth || $aBox[3] > $iHeight)
      {
        $sStr = "";
        $sStr2 = "";
        foreach($aText as $sWord)
        {
          $sStr2 = $sStr2.' '.$sWord;
          $aBox = imagettfbbox($iSize, 0, $sFont, $sStr2);
          
          if($aBox[2] > $iWidth)
          {
            $sStr .= "\n".$sWord;
            $sStr2 = $sStr;
          }
          else
            $sStr = $sStr2;
        }
      }
    }
    else
      break;
  }

  return $sStr;
}

//! создание изображения, вмещение в него sSrcPath, нанесение sText, сохранение в sNewPath
function CreateImg($sSrcPath, $sNewPath, $sText)
{
  $sHeight2 = IMG_HEIGHT-TEXT_HEIGHT;
  $hImg = imagecreatetruecolor(IMG_WIDTH, IMG_HEIGHT);
  $hBlack = imagecolorallocate($hImg, 0, 0, 0);
  $hWhite = imagecolorallocate($hImg, 255, 255, 255);

  imagefilledrectangle($hImg, 0, 0, IMG_WIDTH, IMG_HEIGHT, $hWhite);

  $hSrcImg = LoadImg($sSrcPath);
  $vSizeSrc = getimagesize($sSrcPath);

  //расчет разницы величин
  $iWdiff = $vSizeSrc[0] / IMG_WIDTH;
  $iHdiff = $vSizeSrc[1] / $sHeight2;
  
  //расчет коэффициента масштабирования изображения
  $fCoef = 1;
  if($iWdiff > $iHdiff)
    $fCoef = IMG_WIDTH / $vSizeSrc[0];
  else if($iHdiff > $iWdiff)
    $fCoef = $sHeight2 / $vSizeSrc[1];
  
  $dst_w = $vSizeSrc[0] * $fCoef;
  $dst_h = $vSizeSrc[1] * $fCoef;
  $dst_x = (IMG_WIDTH / 2) - ($dst_w / 2); 
				
  imagecopyresampled($hImg, $hSrcImg, $dst_x, TEXT_HEIGHT, 0, 0, $dst_w, $dst_h, $vSizeSrc[0], $vSizeSrc[1]);

  $iFontSize = FONT_SIZE;
  $sText = ContainText($sText, TEXT_WIDTH, TEXT_HEIGHT, $iFontSize, FONT_FILE);

  $aBox = imagettfbbox($iFontSize, 0, FONT_FILE, $sText);
  $x = $aBox[0] + (imagesx($hImg) / 2) - ($aBox[4] / 2);
  $y = TEXT_HEIGHT - $aBox[1];
  imagettftext($hImg, $iFontSize, 0, $x, $y, $hBlack, FONT_FILE, $sText);
  
  imagejpeg($hImg, $sNewPath);
  imagedestroy($hImg);
}

//##########################################################################

//получаем список файлов (с путями)
$aFiles = ScanPath($sSrcDir);
          
//проходимся по списку файлов
for($k=0, $kl=count($aFiles); $k<$kl; ++$k)
{	
	$sSrcPath = $sSrcDir.DIRECTORY_SEPARATOR.$aFiles[$k];
	$sEx = GetExtension($sSrcPath);
			
  if(GetExtension($sSrcPath) == IMG_EX)
  {
    $sFileName = basename($sSrcPath, "." . $sEx);
    $sNewPath = $sDestDir;
    $sNewPath = $sNewPath.DIRECTORY_SEPARATOR.$sFileName.".".$sEx;
    
    CreateImg($sSrcPath, $sNewPath, $sFileName);
  }
}
