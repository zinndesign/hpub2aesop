// prevent link taps from bubbling up event chain
var links = document.querySelectorAll("a");
for(var i=0; i<links.length; i++) {
	links[i].addEventListener('click', function(evt) {
		evt.stopPropagation();
	});
}
// show HUD when article container div is tapped
document.querySelector(".articleContainer").addEventListener('click', function() {
	window.location = "/";
});