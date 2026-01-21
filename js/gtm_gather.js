// used on form_submit.js
window.pushLead = function({form}){
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
        'event': 'generate_lead',
        'form_location': form
    });
}

// used on form_submit.js
window.pushFailedForm = function({form,failure}){
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
        'event':'failed_form',
        'form_location':form,
        'failure_message':failure
    })
}
// used on project_loading.js
window.pushProject = function({slug}){
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
        'event':'project_view',
        'slug':slug
    })
}
// used on project_details.js
window.pushImgView = function ({image_index,slug}){
    window.dataLayer = window.dataLayer || [];
    window.dataLayer.push({
        'event':'image_view',
        'image_index':image_index,
        'slug':slug
    })
}