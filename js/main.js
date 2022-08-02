function getMeta(metaName) {
  const metas = document.getElementsByTagName('meta');
  for (let i = 0; i < metas.length; i++) {
    if (metas[i].getAttribute('name') === metaName) {
      return metas[i].getAttribute('content');
    }
  }
  return '';
}

// https://www.codegrepper.com/code-examples/javascript/javascript+check+if+json+object+is+valid
function isJson(str) {
  try {
      JSON.parse(str);
  } catch (e) {
      return false;
  }
  return true;
}

function checkIfExists(src, callback) {
  let xhr = new XMLHttpRequest();
  xhr.onreadystatechange = () => {
    if (this.readyState === this.DONE) {
      callback();
    }
  }
  xhr.open('HEAD', src);
}

function include(file) {
  /* 
   * Adapted from the following sources
   * https://www.geeksforgeeks.org/how-to-include-a-javascript-file-in-another-javascript-file/
   * https://stackoverflow.com/questions/3646914/how-do-i-check-if-file-exists-in-jquery-or-pure-javascript
   */
  checkIfExists(file,()=>{
    let script  = document.createElement('script');
    script.src  = file;
    script.type = 'text/javascript';
    script.defer = true;
    document.currentScript.after(script);
  });
}

function logout() {
  $.get('/includes/logout.php?from='+window.location.href,function(data) {
    $('body').append(data);
  });
}

$(document).ready((async () => {
  // If the page has title metadata
  if (getMeta('title') !== "") {
    // Set title 
    document.title = getMeta('title');
    // Dynamically load related script if it exists
    let path = window.location.pathname;
    path = path.substring(0,path.lastIndexOf('.')) || path;
    if (path == '/') {
      include('/js/index.js');
    } else {
      include('/js'+path+'.js');
    }
  }
})());