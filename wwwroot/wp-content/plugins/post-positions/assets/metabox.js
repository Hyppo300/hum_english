
jQuery(document).ready(function($){
var topSlug = pp_metabox.top_cat_slug;


function toggleMetabox() {
var show = false;
$('#categorychecklist input[type=checkbox]').each(function(){
var label = $(this).closest('li').text() || '';
// use value vs slug: check checked categories via data
// better: check by term slug using wp data? fallback to text contains
if ($(this).data('slug') === topSlug || $(this).closest('label').text().toLowerCase().indexOf(topSlug) !== -1) {
if ($(this).is(':checked')) show = true;
}
});


if (show) {
$('#pp_featured_position').closest('#pp-metabox-wrap').show();
} else {
$('#pp_featured_position').closest('#pp-metabox-wrap').hide();
}
}


// initial toggle and on change
toggleMetabox();
$(document).on('change', '#categorychecklist input[type=checkbox], #in-categorychecklist input[type=checkbox]', function(){ toggleMetabox(); });
});