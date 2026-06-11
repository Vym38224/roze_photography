var button = document.querySelector(".button--up");
var limit = 100;

document.addEventListener("scroll", scrollFunction);

function scrollFunction() {
  console.log("scroll");
  if (document.documentElement.scrollTop > limit) {
    button.style.display = "block";
  } else {
    button.style.display = "none";
  }
}

function scrollUp() {
  document.documentElement.scrollTop = 0;
}
