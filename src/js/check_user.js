const tkn = AkanaCookie.get("tkn");

if(tkn == null){
    window.location.replace('/index.html?r=0');
}