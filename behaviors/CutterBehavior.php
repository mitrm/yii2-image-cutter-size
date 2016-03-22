<?php

namespace mitrm\cutter\behaviors;

use Yii;
use yii\helpers\Json;
use yii\image\ImageDriver;
use yii\imagine\Image;
use Imagine\Image\Box;
use Imagine\Image\Point;
use yii\db\ActiveRecord;
use yii\web\UploadedFile;

/**
 * Class CutterBehavior
 * @package mitrm\cutter\behavior
 */
class CutterBehavior extends \yii\behaviors\AttributeBehavior
{
    /**
     * Attributes
     * @var
     */
    public $attributes;

    /**
     * Base directory
     * @var
     */
    public $baseDir;

    /**
     * Base path
     * @var
     */
    public $basePath;

    /**
     * Image cut quality
     * @var int
     */
    public $quality = 92;

    public function events()
    {
        return [
            ActiveRecord::EVENT_BEFORE_INSERT => 'beforeUpload',
            ActiveRecord::EVENT_BEFORE_UPDATE => 'beforeUpload',
            ActiveRecord::EVENT_BEFORE_DELETE => 'beforeDelete',
        ];
    }

    public function beforeUpload()
    {
        if (is_array($this->attributes) && count($this->attributes)) {
            foreach ($this->attributes as $attribute) {
                $this->upload($attribute);
            }
        } else {
            $this->upload($this->attributes);
        }
    }

    public function upload($attribute)
    {
        if ($uploadImage = UploadedFile::getInstance($this->owner, $attribute)) {
            if (!$this->owner->isNewRecord) {
                $this->delete($attribute);
            }

            $cropping = $_POST[$attribute . '-cropping'];

            $croppingFileName = md5($uploadImage->name . $this->quality . Json::encode($cropping));
            $croppingFileExt = '.png';

            $croppingFileBasePath = Yii::getAlias($this->basePath);

            if (!is_dir($croppingFileBasePath)) {
                mkdir($croppingFileBasePath, 0755, true);
            }
            $croppingFilePath = Yii::getAlias($this->basePath);

            if (!is_dir($croppingFilePath)) {
                mkdir($croppingFilePath, 0755, true);
            }

            $croppingFile = $croppingFilePath . DIRECTORY_SEPARATOR . $croppingFileName . $croppingFileExt;

            $imageTmp = Image::getImagine()->open($uploadImage->tempName);
            $imageTmp->rotate($cropping['dataRotate']);

            $image = Image::getImagine()->create($imageTmp->getSize());
            $image->paste($imageTmp, new Point(0, 0));

            $point = new Point($cropping['dataX'], $cropping['dataY']);
            $box = new Box($cropping['dataWidth'], $cropping['dataHeight']);

            $image->crop($point, $box);
            $image->save($croppingFile, ['quality' => $this->quality]);

            $this->owner->{$attribute} = $croppingFileName;
        } elseif (isset($_POST[$attribute . '-remove']) && $_POST[$attribute . '-remove']) {
            $this->delete($attribute);
        } elseif (isset($this->owner->oldAttributes[$attribute])) {
            $this->owner->{$attribute} = $this->owner->oldAttributes[$attribute];
        }
    }

    public function beforeDelete()
    {
        if (is_array($this->attributes) && count($this->attributes)) {
            foreach ($this->attributes as $attribute) {
                $this->delete($attribute);
            }
        } else {
            $this->delete($this->attributes);
        }
    }

    public function delete($attribute)
    {
        $mack = Yii::getAlias($this->basePath) . DIRECTORY_SEPARATOR . $this->owner->$attribute . '*';
        array_map("unlink", glob($mack));
    }

    /**
     * @brief Отдает оригинал загруженного изображения
     * @return string
     */
    public function getImgOrigin()
    {
        $attribute = $this->attributes;
        return $this->baseDir.'/'.$this->owner->$attribute.'.png';
    }

    /**
     * @brief Отдает изображение под нужный размер
     * @detailed смотри self::getImg()
     * @param int $size
     * @return bool|string
     */
    public function getImg($size=500)
    {
        $attribute = $this->attributes;
        return self::getImgUrl($this->owner->$attribute, $size);
    }

    /**
     * @brief Отдает изображение под нужный размер
     * @detailed если изображения нет под нужный размер, генерирует его в реальном времени
     * @param $img
     * @param int $size
     * @return bool|string
     */
    public function getImgUrl($img, $size=500)
    {
        $image = $this->baseDir.'/'.$img.'_'.$size.'x'.$size.'.png';
        $image_path = $this->basePath.'/'.$img.'_'.$size.'x'.$size.'.png';
        if(file_exists($image_path) && false) {
            return $image;
        } else {
            $file = $this->basePath.'/'.$img.'.png';
            if(!file_exists($file)) {
                return false;
            }
            $image = new ImageDriver(['driver' => 'GD']);
            $image = $image->load($file);
            $image->resize($size,$size);
            $image->save($this->basePath.'/'.$img.'_'.$size.'x'.$size.'.png', 100);
            return $this->baseDir.'/'.$img.'_'.$size.'x'.$size.'.png';
        }
    }

}
