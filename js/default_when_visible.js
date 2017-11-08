autoPopulateFields.default_when_visible.init = function() {
    // Setting branching logic to do not show messages.
    showEraseValuePrompt = 0;

    // Extracting evalLogic function body.
    var evalLogicBody = evalLogic.toString();
    var evalLogicBody = evalLogicBody.slice(evalLogicBody.indexOf('{') + 1, evalLogicBody.lastIndexOf('}'));

    // Changing evalLogic() function behavior: hide fields even when the message is not shown.
    var target = 'var eraseIt = false;';
    var replacement = 'var eraseIt = false; document.getElementById(this_field + \'-tr\').style.display = \'none\';';

    // Overriding original function.
    evalLogic = new Function('this_field', 'byPassEraseFieldPrompt', 'logic', evalLogicBody.replace(target, replacement));

    // Creating another version of evalLogic() that erases fields when message is not shown.
    var evalLogicSubmit = new Function('this_field', 'byPassEraseFieldPrompt', 'logic', evalLogicBody.replace(target, 'var eraseIt = true;'));

    // Overriding formSubmitDataEntry() in order to erase not visible branching
    // logic fields before saving data.
    var oldFormSubmitDataEntry = formSubmitDataEntry;
    formSubmitDataEntry = function() {
        $.each(autoPopulateFields.default_when_visible.branchingTargets, function(index, fieldName) {
            if (!$('#' + fieldName + '-tr').is(':visible')) {
                evalLogicSubmit(fieldName, false, false);
            }
        });

        oldFormSubmitDataEntry();
    }
};

autoPopulateFields.default_when_visible.init();
