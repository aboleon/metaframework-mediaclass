$(function() {

    /* ------------------------------------
    *  GESTION DU JCROP
    * ------------------------------------ */

    function showCoords(c)
    {
        jQuery('#x1').val(c.x);
        jQuery('#y1').val(c.y);
        jQuery('#w').val(parseInt(c.w));
        jQuery('#h').val(parseInt(c.h));
    };

    var wi_w = parseInt($('#wi').val()),
    wi_h = parseInt($('#he').val()),
    temp_w = parseInt($('input[name=resized_temp_w').val()),
    temp_h = parseInt($('input[name=resized_temp_h').val()),
    api;

    console.log(wi_w +' '+wi_h);
    console.log(temp_w +' '+temp_h);

    $('#crop_image').Jcrop({
        onChange: showCoords,
        onSelect: showCoords,
        aspectRatio: isNaN(wi_w) || isNaN(wi_h) ? 0 : wi_w/wi_h,
        minSize: isNaN(wi_w) || isNaN(wi_h) ? 0 : [wi_w,wi_h],
        boxWidth: 1100,
        boxHeight: 800

    },function(){
        api = this;
        api.setSelect([130,65,130+350,65+285]);
        api.setOptions({ bgFade: true, allowResize: true, trueSize: [temp_w, temp_h] });
        api.ui.selection.addClass('jcrop-selection');
    });

});