


// if (window.matchMedia("(max-width: 700px)").matches) { // If media query matches

//     document.body.style.backgroundColor = "yellow";
//     $("test").insertAfter(".slicknav_brand");

// } else {
//     document.body.style.backgroundColor = "pink";
// }

$(window).ready(function () {

    // mobile version
    if (320 <= $(window).width() <= 767) {

        $(".header-social-tablet").removeClass("hidden");
        $(".header-social-tablet").insertAfter(".slicknav_brand");

    } else {
        $(".header-social-tablet").addClass("hidden");
        $(".profil_mobile").addClass("hidden");
    }

});