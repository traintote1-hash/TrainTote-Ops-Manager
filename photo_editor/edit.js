// ======================================
// IMAGE STUDIO V1
// ======================================

//
// Image
//

const editorImage =
    document.getElementById(
        'editorImage'
    );


//
// Rotation
//

let currentRotation = 0;

let flipX = 1;

let flipY = 1;


//
// Crop
//

let cropMode = false;

let draggingCrop = false;

let resizingCrop = false;

let resizeCorner = '';


//
// Undo / Redo
//

let undoStack = [];

let redoStack = [];


//
// Original image
//

const originalImage =
    editorImage.src;


//
// Background removed image
//

let removedBackgroundImage =
    null;


//
// Crop references
//

const cropOverlay =
    document.getElementById(
        'cropOverlay'
    );

const cropBox =
    document.getElementById(
        'cropBox'
    );


//
// Grid
//

const gridOverlay =
    document.getElementById(
        'gridOverlay'
    );

const showGrid =
    document.getElementById(
        'showGrid'
    );


//
// Hidden fields
//

const rotationField =
    document.getElementById(
        'rotationField'
    );

const flipHorizontalField =
    document.getElementById(
        'flipHorizontalField'
    );

const flipVerticalField =
    document.getElementById(
        'flipVerticalField'
    );

const cropXField =
    document.getElementById(
        'cropXField'
    );

const cropYField =
    document.getElementById(
        'cropYField'
    );

const cropWidthField =
    document.getElementById(
        'cropWidthField'
    );

const cropHeightField =
    document.getElementById(
        'cropHeightField'
    );


//
// Startup
//

cropOverlay.style.display = 'none';

console.log(
    'Image Studio V1 Loaded'
);

// ======================================
// UPDATE IMAGE
// ======================================

function updateImage() {

    editorImage.style.transform =

        `rotate(${currentRotation}deg)
         scaleX(${flipX})
         scaleY(${flipY})`;

    rotationField.value =
        currentRotation;

    flipHorizontalField.value =
        (flipX === -1)
            ? 1
            : 0;

    flipVerticalField.value =
        (flipY === -1)
            ? 1
            : 0;

}


// ======================================
// ROTATE BUTTONS
// ======================================

document

.querySelectorAll(

    '.rotate-btn'

)

.forEach(

    button => {

        button.addEventListener(

            'click',

            function () {

                saveState();

                currentRotation +=

                    parseInt(

                        this.dataset.angle

                    );

                updateImage();

            }

        );

    }

);


// ======================================
// 180°
// ======================================

document

.getElementById(

    'rotate180'

)

.addEventListener(

    'click',

    function () {

        saveState();

        currentRotation += 180;

        updateImage();

    }

);


// ======================================
// RESET
// ======================================

document

.getElementById(

    'resetRotation'

)

.addEventListener(

    'click',

    function () {

        saveState();

        currentRotation = 0;

        flipX = 1;

        flipY = 1;

        updateImage();

    }

);


// ======================================
// FLIP HORIZONTAL
// ======================================

document

.getElementById(

    'flipHorizontal'

)

.addEventListener(

    'click',

    function () {

        saveState();

        flipX *= -1;

        updateImage();

    }

);


// ======================================
// FLIP VERTICAL
// ======================================

document

.getElementById(

    'flipVertical'

)

.addEventListener(

    'click',

    function () {

        saveState();

        flipY *= -1;

        updateImage();

    }

);


// ======================================
// GRID
// ======================================

showGrid.addEventListener(

    'change',

    function () {

        gridOverlay.style.display =

            this.checked

                ? 'block'

                : 'none';

    }

);


// ======================================
// INITIALIZE
// ======================================

updateImage();

// ======================================
// SAVE STATE
// ======================================

function saveState() {

    undoStack.push({

        rotation: currentRotation,

        flipX: flipX,

        flipY: flipY,

        image: editorImage.src

    });

    redoStack = [];

}


// ======================================
// UNDO
// ======================================

document

.getElementById(

    'undoButton'

)

.addEventListener(

    'click',

    function () {

        if (

            undoStack.length === 0

        ) {

            return;

        }

        redoStack.push({

            rotation: currentRotation,

            flipX: flipX,

            flipY: flipY,

            image: editorImage.src

        });

        let state =

            undoStack.pop();

        currentRotation =

            state.rotation;

        flipX =

            state.flipX;

        flipY =

            state.flipY;

        editorImage.src =

            state.image;

        updateImage();

    }

);


// ======================================
// REDO
// ======================================

document

.getElementById(

    'redoButton'

)

.addEventListener(

    'click',

    function () {

        if (

            redoStack.length === 0

        ) {

            return;

        }

        undoStack.push({

            rotation: currentRotation,

            flipX: flipX,

            flipY: flipY,

            image: editorImage.src

        });

        let state =

            redoStack.pop();

        currentRotation =

            state.rotation;

        flipX =

            state.flipX;

        flipY =

            state.flipY;

        editorImage.src =

            state.image;

        updateImage();

    }

);


// ======================================
// RESTORE ORIGINAL
// ======================================

document

.getElementById(

    'restoreOriginal'

)

.addEventListener(

    'click',

    function () {

        if (

            !confirm(

                'Restore original image?'

            )

        ) {

            return;

        }

        currentRotation = 0;

        flipX = 1;

        flipY = 1;

        editorImage.src = originalImage;

        removedBackgroundImage = null;

        undoStack = [];

        redoStack = [];

        updateImage();

    }

);

// ======================================
// CROP MODE
// ======================================

const cropButton =
    document.getElementById(
        'cropButton'
    );


//
// Create Apply Crop button
//

const applyCropButton =
    document.createElement(
        'button'
    );

applyCropButton.innerText =
    'Apply Crop';

applyCropButton.className =
    'btn btn-success mt-3';

applyCropButton.style.display =
    'none';


//
// Create Cancel Crop button
//

const cancelCropButton =
    document.createElement(
        'button'
    );

cancelCropButton.innerText =
    'Cancel Crop';

cancelCropButton.className =
    'btn btn-secondary mt-3 ms-2';

cancelCropButton.style.display =
    'none';


//
// Add buttons under image panel
//

document

.querySelector(

    '.image-panel'

)

.appendChild(

    applyCropButton

);

document

.querySelector(

    '.image-panel'

)

.appendChild(

    cancelCropButton

);


//
// Enter Crop Mode
//

cropButton.addEventListener(

    'click',

    function () {

        cropMode = true;

        cropOverlay.style.display =
            'block';

        cropBox.style.display =
            'block';

        applyCropButton.style.display =
            '';

        cancelCropButton.style.display =
            '';

    }

);


//
// Cancel Crop
//

cancelCropButton.addEventListener(

    'click',

    function () {

        cropMode = false;

        cropOverlay.style.display =
            'none';

        cropBox.style.display =
            'none';

        applyCropButton.style.display =
            'none';

        cancelCropButton.style.display =
            'none';

    }

);


//
// Default crop rectangle
//

cropBox.style.left = '100px';

cropBox.style.top = '100px';

cropBox.style.width = '300px';

cropBox.style.height = '200px';

// ======================================
// DRAG CROP BOX
// ======================================

let dragging = false;

let dragOffsetX = 0;

let dragOffsetY = 0;


cropBox.addEventListener(

    'mousedown',

    function (e) {

        if (

            e.target.classList.contains(
                'crop-handle'
            )

        ) {

            return;

        }

        dragging = true;

        dragOffsetX =
            e.offsetX;

        dragOffsetY =
            e.offsetY;

    }

);


document.addEventListener(

    'mouseup',

    function () {

        dragging = false;

        resizingCrop = false;

    }

);


document.addEventListener(

    'mousemove',

    function (e) {

        if (

            dragging

        ) {

            let rect =

                cropOverlay.getBoundingClientRect();

            cropBox.style.left =

                (e.clientX -
                 rect.left -
                 dragOffsetX)

                + 'px';

            cropBox.style.top =

                (e.clientY -
                 rect.top -
                 dragOffsetY)

                + 'px';

        }

    }

);


// ======================================
// RESIZE HANDLES
// ======================================

document

.querySelectorAll(

    '.crop-handle'

)

.forEach(

    handle => {

        handle.addEventListener(

            'mousedown',

            function (e) {

                e.stopPropagation();

                resizingCrop = true;

                resizeCorner =

                    this.classList[1];

            }

        );

    }

);


// ======================================
// RESIZE
// ======================================

document.addEventListener(

    'mousemove',

    function (e) {

        if (

            !resizingCrop

        ) {

            return;

        }

        let rect =

            cropOverlay.getBoundingClientRect();

        let x =

            e.clientX -
            rect.left;

        let y =

            e.clientY -
            rect.top;

        let left =

            cropBox.offsetLeft;

        let top =

            cropBox.offsetTop;

        let width =

            cropBox.offsetWidth;

        let height =

            cropBox.offsetHeight;


        //
        // SE
        //

        if (

            resizeCorner === 'se'

        ) {

            cropBox.style.width =

                (x - left)

                + 'px';

            cropBox.style.height =

                (y - top)

                + 'px';

        }


        //
        // SW
        //

        if (

            resizeCorner === 'sw'

        ) {

            cropBox.style.left =

                x + 'px';

            cropBox.style.width =

                (width + (left - x))

                + 'px';

            cropBox.style.height =

                (y - top)

                + 'px';

        }


        //
        // NE
        //

        if (

            resizeCorner === 'ne'

        ) {

            cropBox.style.top =

                y + 'px';

            cropBox.style.height =

                (height + (top - y))

                + 'px';

            cropBox.style.width =

                (x - left)

                + 'px';

        }


        //
        // NW
        //

        if (

            resizeCorner === 'nw'

        ) {

            cropBox.style.left =

                x + 'px';

            cropBox.style.top =

                y + 'px';

            cropBox.style.width =

                (width + (left - x))

                + 'px';

            cropBox.style.height =

                (height + (top - y))

                + 'px';

        }

    }

);

// ======================================
// APPLY CROP
// ======================================

applyCropButton.addEventListener(

    'click',

    function () {

        saveState();

        //
        // Create canvas
        //

        let canvas =
            document.createElement(
                'canvas'
            );

        let ctx =
            canvas.getContext(
                '2d'
            );

        //
        // Convert crop box coordinates
        // from displayed image to real image
        //

        let imageRect =
            editorImage.getBoundingClientRect();

        let overlayRect =
            cropOverlay.getBoundingClientRect();

        let scaleX =
            editorImage.naturalWidth /
            editorImage.offsetWidth;

        let scaleY =
            editorImage.naturalHeight /
            editorImage.offsetHeight;

        let x =
            (
                cropBox.offsetLeft -
                (
                    imageRect.left -
                    overlayRect.left
                )
            ) * scaleX;

        let y =
            (
                cropBox.offsetTop -
                (
                    imageRect.top -
                    overlayRect.top
                )
            ) * scaleY;

        let width =
            cropBox.offsetWidth *
            scaleX;

        let height =
            cropBox.offsetHeight *
            scaleY;


        //
        // Prevent negative crop
        //

        x = Math.max(
            0,
            x
        );

        y = Math.max(
            0,
            y
        );


        //
        // Size canvas
        //

        canvas.width =
            width;

        canvas.height =
            height;


        //
        // Draw cropped image
        //

        ctx.drawImage(

            editorImage,

            x,
            y,
            width,
            height,

            0,
            0,
            width,
            height

        );


        //
        // Replace image
        //

        editorImage.src =

            canvas.toDataURL(
                'image/png'
            );


        //
        // Exit crop mode
        //

        cropMode = false;

        cropOverlay.style.display =
            'none';

        cropBox.style.display =
            'none';

        applyCropButton.style.display =
            'none';

        cancelCropButton.style.display =
            'none';

    }

);

// ======================================
// REMOVE BACKGROUND
// ======================================

const removeBackgroundButton =

    document.getElementById(
        'removeBackground'
    );


removeBackgroundButton.addEventListener(

    'click',

    async function () {

        saveState();

        removeBackgroundButton.disabled =
            true;

        removeBackgroundButton.innerText =
            'Removing Background...\nPlease Wait...';

        try {

            //
            // Build canvas from current image
            //

            let canvas =
                document.createElement(
                    'canvas'
                );

            canvas.width =
                editorImage.naturalWidth;

            canvas.height =
                editorImage.naturalHeight;

            let ctx =
                canvas.getContext(
                    '2d'
                );

            ctx.drawImage(

                editorImage,

                0,

                0

            );


            //
            // Convert to base64
            //

            let imageData =

                canvas.toDataURL(
                    'image/png'
                );


            //
            // Send to PHP
            //

            let formData =
                new FormData();

            formData.append(
                'image',
                imageData
            );


            let response =

                await fetch(

                    'remove_background.php',

                    {

                        method: 'POST',

                        body: formData

                    }

                );


            let data =

                await response.json();


            //
            // Failed
            //

            if (

                !data.success

            ) {

                alert(

                    data.error ||

                    'Background removal failed.'

                );

                removeBackgroundButton.innerText =
                    'Remove Background';

                removeBackgroundButton.disabled =
                    false;

                return;

            }


            //
            // Success
            //

            editorImage.src =
                data.image;

        }

        catch (

            err

        ) {

            console.log(
                err
            );

            alert(

                'Background removal failed.'

            );

        }


        removeBackgroundButton.innerText =
            'Remove Background';

        removeBackgroundButton.disabled =
            false;

    }

);

// ======================================
// DONE
// ======================================

document

.getElementById(

    'saveButton'

)

.addEventListener(

    'click',

    function () {

        //
        // Save rotation
        //

        rotationField.value =
            currentRotation;


        //
        // Save flips
        //

        flipHorizontalField.value =

            (flipX === -1)

                ? 1

                : 0;


        flipVerticalField.value =

            (flipY === -1)

                ? 1

                : 0;


        //
        // Save current image
        //

        let canvas =

            document.createElement(
                'canvas'
            );


        canvas.width =
            editorImage.naturalWidth;


        canvas.height =
            editorImage.naturalHeight;


        let ctx =

            canvas.getContext(
                '2d'
            );


        ctx.drawImage(

            editorImage,

            0,

            0

        );


        //
        // Add image data to form
        //

        let imageInput =

            document.createElement(
                'input'
            );


        imageInput.type =
            'hidden';


        imageInput.name =
            'image_data';


        imageInput.value =

            canvas.toDataURL(
                'image/png'
            );


        document

        .getElementById(
            'saveForm'
        )

        .appendChild(
            imageInput
        );


        //
        // Submit
        //

        document

        .getElementById(

            'saveForm'

        )

        .submit();

    }

);


// ======================================
// STARTUP
// ======================================

updateImage();

console.log(

    'Image Studio V1 Ready'

);