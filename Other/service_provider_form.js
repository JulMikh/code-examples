
$('#add-new-service-provider input.form-control, #add-new-service-provider select.form-control').on('change', function(){
   checkDocumentFields();
});    
$('#add-new-service-provider #firm').on('blur', function(){
	checkDocumentFields();
});    

$('#relationship').click(function(){
	var checkbox = $('#relationship');
	
	if (checkbox[0].checked) {
		$('#relationship_duration').attr('disabled','disabled');
		var date = new Date();
		$('#rel').val((date.getMonth()+1) + '/' + date.getDate() + '/' + date.getFullYear());
		$('#rel').addClass('changed');
		checkDocumentFields();
	} else {
		$('#relationship_duration').removeAttr('disabled');
		setRel();
	}
});
$('#relationship_duration').change(function(){
	setRel();
});

$('#provider_type_id').change(function(){
	var id = $('#provider_type_id option:selected').val();
	
	checkDocumentFields();
    $( "#firm" ).autocomplete({
        source: availableFirms[id]
    });
});

$('.suggestion select').change(function(){
	$('#firm').val($(this).val());
	checkDocumentFields();
});	
	
function setRel()
{
	var relationship_duration = $('#relationship_duration').val();
	if (relationship_duration != '') {
		$('#rel').val(relationship_duration);
		$('#rel').addClass('changed');
	} else { 
		$('#rel').removeClass('changed');
		$('#rel').val('');
	}
	checkDocumentFields();
}

function checkDocumentFields(){
    var res = true;
    $('#add-new-service-provider input.form-control').each(function(){
    	var str = $.trim($(this).val());
        if (str!='' && str!="0" && !$(this).hasClass('error')) 
        {
            $(this).addClass('changed');
            $(this).siblings('label.placeholder-check').removeClass('hidden');
            $(this).parent().siblings('label.placeholder-check').removeClass('hidden');
            
        } else {
        	if (!$(this).hasClass('error')) $(this).removeClass('changed');
        	$(this).siblings('label.placeholder-check').addClass('hidden');
        	$(this).parent().siblings('label.placeholder-check').addClass('hidden');
        }
    	if($(this).hasClass('required')) if(!$(this).hasClass('changed') || $(this).hasClass('error'))  {
            res = false;
        }
    });
    
    if ($('#rel').val()=='') res = false;
    	
    $('#add-new-service-provider select.form-control').each(function(){
    	var str = $.trim($(this).val());
        if (str!='' && str!="0") 
        {
            $(this).addClass('changed');
            $(this).next('label.placeholder-check').removeClass('hidden');
            
            $(this).siblings('label.placeholder-check').removeClass('hidden');
            $(this).siblings('div.bootstrap-select').children().siblings('button.selectpicker').children().siblings('span.caret').addClass('hidden');
            $(this).parent().siblings('label.placeholder-check').removeClass('hidden');
        } else {
        	if ($(this).hasClass('required')) $(this).removeClass('changed');
        	$(this).siblings('label.placeholder-check').addClass('hidden');
        	$(this).siblings('div.bootstrap-select').children().siblings('button.selectpicker').children().siblings('span.caret').removeClass('hidden');
        	if ($(this).parent().siblings('input.required').val()=='') $(this).parent().siblings('label.placeholder-check').addClass('hidden');
        }
    	if($(this).hasClass('required')) if(!$(this).hasClass('changed'))  {
            res = false;
        }
    });
    
    if (res) {
        $('#add-new-service-provider #submit').removeAttr('disabled');
    } else {
    	$('#add-new-service-provider #submit').attr('disabled','disabled');
    }         
}

$('#add-new-service-provider #cancel').click(function(){
	$('#provider-information').toggle();
	$('#add-new-service-provider').hide();
});


$('div.provider-info').hover(function(){
	$(this).find('span.edit-profile').css('background','#A6BFC7');
	$(this).find('span.view-profile').css('background','#A6BFC7');
},function(){
	$(this).find('span.edit-profile').css('background','#8fa6ad');
	$(this).find('span.view-profile').css('background','#8fa6ad');
});

$(document).ready(function(){  
	
    $("#telephone").intlTelInput({
        utilsScript: "/js/tel-input/utils.js",
        nationalMode: false
    });
    var telInput = $("#telephone");
    var errorMsg = telInput.parent().parent().find('.help-block');
    
    telInput.blur(function() {
        if ($.trim(telInput.val())) {
            if (telInput.intlTelInput("isValidNumber")) {
                telInput.parent().parent().removeClass("has-error");
                telInput.removeClass("error");
                telInput.parent().siblings('label.placeholder-check').removeClass('hidden');
                errorMsg.addClass('hide');
            } else {
                telInput.parent().parent().addClass("has-error");
                telInput.addClass("error");
                telInput.parent().siblings('label.placeholder-check').addClass('hidden');
                errorMsg.removeClass('hide');
                errorMsg.show()
            }
        }
        checkDocumentFields();
    });
    
    telInput.keydown(function() {
        telInput.removeClass("error");
        errorMsg.addClass('hide')
        telInput.parent().parent().removeClass("has-error");
    });

    
    $("#fax").intlTelInput({
        utilsScript: "/js/tel-input/utils.js",
        nationalMode: false
    });
    var faxInput = $("#fax");
    var errorMsg = faxInput.parent().parent().find('.help-block');
    
    faxInput.blur(function() {
        if ($.trim(faxInput.val())) {
            if (faxInput.intlTelInput("isValidNumber")) {
            	faxInput.parent().parent().removeClass("has-error");
            	faxInput.removeClass("error");
            	faxInput.siblings('label.placeholder-check').removeClass('hidden');
                errorMsg.addClass('hide');
            } else {
            	faxInput.parent().parent().addClass("has-error");
            	faxInput.addClass("error");
                faxInput.siblings('label.placeholder-check').addClass('hidden');
                errorMsg.removeClass('hide');
                errorMsg.show()
            }
        }
    });
    
    faxInput.keydown(function() {
    	faxInput.removeClass("error");
        errorMsg.addClass('hide')
        faxInput.parent().parent().removeClass("has-error");
    });
    
    $('#telephone').after('<label class="placeholder placeholder_telephone" for="telephone">Phone <span>*</span></label>\
							<label class="placeholder-check hidden" ><i class="fa fa-check"></i></label>');
    $('#fax').after('<label class="placeholder placeholder_fax" for="fax">Fax</label>\
							<label class="placeholder-check hidden" ><i class="fa fa-check"></i></label>');
    

   
});