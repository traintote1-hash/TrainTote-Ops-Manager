<?php

session_start();

require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {

    die('Not authorized.');

}

$type = $_POST['type'] ?? '';

$id = intval($_POST['id'] ?? 0);

if (!in_array($type, ['equipment', 'industry', 'temp'])) {

    die('Invalid type.');

}


/*
|--------------------------------------------------------------------------
| Find Image
|--------------------------------------------------------------------------
*/

if ($type === 'equipment') {

    $stmt = $pdo->prepare("

        SELECT photo_filename

        FROM equipment

        WHERE id = ?

        LIMIT 1

    ");

}

elseif ($type === 'industry') {

    $stmt = $pdo->prepare("

        SELECT photo_filename

        FROM industries

        WHERE id = ?

        LIMIT 1

    ");

}

else {

    die('Temp not implemented.');

}


$stmt->execute([$id]);

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {

    die('Photo not found.');

}


$imagePath =

    dirname(__DIR__) .

    '/uploads/' .

    $row['photo_filename'];


if (!file_exists($imagePath)) {

    die('Image file missing.');

}


/*
|--------------------------------------------------------------------------
| Load Image
|--------------------------------------------------------------------------
*/

$image = new Imagick();

$image->readImage(

    $imagePath

);


/*
|--------------------------------------------------------------------------
| Values
|--------------------------------------------------------------------------
*/

$rotation =

    floatval(

        $_POST['rotation'] ?? 0

    );

$flipHorizontal =

    intval(

        $_POST['flip_horizontal'] ?? 0

    );

$flipVertical =

    intval(

        $_POST['flip_vertical'] ?? 0

    );

$cropX =

    intval(

        $_POST['crop_x'] ?? 0

    );

$cropY =

    intval(

        $_POST['crop_y'] ?? 0

    );

$cropWidth =

    intval(

        $_POST['crop_width'] ?? 0

    );

$cropHeight =

    intval(

        $_POST['crop_height'] ?? 0

    );
	
	/*
|--------------------------------------------------------------------------
| Rotate
|--------------------------------------------------------------------------
*/

if (

    abs($rotation) > 0.01

) {

    $image->setImageBackgroundColor(

        new ImagickPixel(

            'transparent'

        )

    );

    $image->rotateImage(

        new ImagickPixel(

            'transparent'

        ),

        $rotation

    );

}


/*
|--------------------------------------------------------------------------
| Flip Horizontal
|--------------------------------------------------------------------------
*/

if (

    $flipHorizontal

) {

    $image->flopImage();

}


/*
|--------------------------------------------------------------------------
| Flip Vertical
|--------------------------------------------------------------------------
*/

if (

    $flipVertical

) {

    $image->flipImage();

}


/*
|--------------------------------------------------------------------------
| Reset Canvas After Rotation
|--------------------------------------------------------------------------
*/

$image->setImagePage(

    0,

    0,

    0,

    0

);

/*
|--------------------------------------------------------------------------
| Crop
|--------------------------------------------------------------------------
*/

if (

    $cropWidth > 0

    &&

    $cropHeight > 0

) {

    /*
    ------------------------------------------------------------
    Prevent crop outside image
    ------------------------------------------------------------
    */

    $imgWidth =

        $image->getImageWidth();

    $imgHeight =

        $image->getImageHeight();


    if (

        $cropX < 0

    ) {

        $cropX = 0;

    }


    if (

        $cropY < 0

    ) {

        $cropY = 0;

    }


    if (

        $cropX + $cropWidth > $imgWidth

    ) {

        $cropWidth =

            $imgWidth - $cropX;

    }


    if (

        $cropY + $cropHeight > $imgHeight

    ) {

        $cropHeight =

            $imgHeight - $cropY;

    }


    /*
    ------------------------------------------------------------
    Crop Image
    ------------------------------------------------------------
    */

    $image->cropImage(

        $cropWidth,

        $cropHeight,

        $cropX,

        $cropY

    );


    /*
    ------------------------------------------------------------
    Reset Virtual Canvas
    ------------------------------------------------------------
    */

    $image->setImagePage(

        0,

        0,

        0,

        0

    );

}

 /*
|--------------------------------------------------------------------------
| Strip Metadata
|--------------------------------------------------------------------------
*/

$image->stripImage();


/*
|--------------------------------------------------------------------------
| Resize Large Images
|--------------------------------------------------------------------------
*/

$maxDimension = 1800;

$width =

    $image->getImageWidth();

$height =

    $image->getImageHeight();


if (

    $width > $maxDimension

    ||

    $height > $maxDimension

) {

    $image->thumbnailImage(

        $maxDimension,

        $maxDimension,

        true,

        true

    );

}


/*
|--------------------------------------------------------------------------
| Compression
|--------------------------------------------------------------------------
*/

$image->setImageCompression(

    Imagick::COMPRESSION_JPEG

);

$image->setImageCompressionQuality(

    85

);


/*
|--------------------------------------------------------------------------
| Progressive JPEG
|--------------------------------------------------------------------------
*/

$image->setInterlaceScheme(

    Imagick::INTERLACE_PLANE

);


/*
|--------------------------------------------------------------------------
| Remove Alpha For JPG
|--------------------------------------------------------------------------
*/

if (

    strtolower(

        $image->getImageFormat()

    ) === 'jpeg'

    ||

    strtolower(

        $image->getImageFormat()

    ) === 'jpg'

) {

    $image->setImageBackgroundColor(

        new ImagickPixel(

            'white'

        )

    );

    $image =

        $image->mergeImageLayers(

            Imagick::LAYERMETHOD_FLATTEN

        );

}

/*
|--------------------------------------------------------------------------
| Save Image
|--------------------------------------------------------------------------
*/

$image->writeImage(

    $imagePath

);


/*
|--------------------------------------------------------------------------
| Cleanup
|--------------------------------------------------------------------------
*/

$image->clear();

$image->destroy();


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

if (

    $type === 'equipment'

) {

    header(

        'Location: ../equipment/view.php?id=' .

        $id

    );

    exit;

}


if (

    $type === 'industry'

) {

    header(

        'Location: ../industries/view.php?id=' .

        $id

    );

    exit;

}


/*
|--------------------------------------------------------------------------
| Temp Image
|--------------------------------------------------------------------------
*/

header(

    'Location: ../equipment/add.php'

);

exit;