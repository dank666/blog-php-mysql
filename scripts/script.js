const d = new Date();

// 修复月份偏移问题
var DATE = String(d.getDate()) + '/' + String(d.getMonth() + 1) + '/' + String(d.getFullYear());

var TIME = d.toLocaleString("en-US",
  {
    hour: "numeric",
    minute: "numeric",
    hour12: true
  }
);

// console.log(DATE);
// console.log(TIME);

var dateTime = DATE + ', ' + TIME;

document.getElementById("blogDate").value = dateTime;