function ajaxFailed(e) {
              console.warn('Błąd połączenia ajax...');
              document.getElementById('wyslij-wiadomosc').removeEventListener('submit', sendForm);
              alert(e);
            };

            function sendForm(e) {
              
              e.preventDefault(); //zatrzymanie akcji wyslania formularza
              let currentID = this.id;
              /* var dataToSend = serialize(contactForm); */
              var dataToSend = new FormData(contactForm);
              var request = new XMLHttpRequest();
              var urlrequest = location.href+'#'+currentID+'-request';
//              alert(urlrequest);
              request.open('POST', urlrequest, true);
              /* request.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8'); */
              request.send(dataToSend);

              request.onload = function() {
                //start 
                if (this.status >= 200 && this.status < 400) {

                  var responseDOM = new DOMParser(); //pobieranie odpowiedzi serwera
                  var responseHTML = responseDOM.parseFromString(this.responseText, 'text/html'); //przetwarzanie odpowiedzi na dokument HTML
                  var response = responseHTML.getElementById(currentID+'-request').outerHTML; //pobranie odpowiedzi serwera - lista błędów

                  document.getElementById(currentID+'-request').outerHTML = response;

                  // warunek dla poprawnie wypelnionych danych - jeżeli spelniony, to ukrywa formularz i wyswietla komunikat o powodzeniu
                  if ( responseHTML.getElementById('placeholder-sent').getAttribute('data-send') === 'true' ) {
                           
                    sentResponse = responseHTML.getElementById(currentID+'-sent').outerHTML; //pobranie odpowiedzi serwera o powodzeniu wyslania
                    document.getElementById('placeholder-sent').outerHTML = sentResponse; //umieszczenie komunikatu o powodzeniu wyslania
                    document.getElementById(currentID).style.opacity = "0.5"; //ukrycie formularza
                    document.getElementById(currentID).style.pointerEvents = "none"; //ukrycie formularza

                    // window.scroll({top: document.getElementById('support-form').offsetTop+40, left: 0, behavior: 'smooth' }); //animacja przewinięcia do komunikatu
                
                    console.log('Wysłano wiadomość!');
            
                    var closeBtn = document.querySelector('#close');                    
                    closeBtn.addEventListener('click', function(){
                        var msgBox = document.querySelector('#formularz-sent');
                        msgBox.style.display = "none";
                    })
      
                    setTimeout(function(){
                        document.getElementById('p24link').click();
                    }, 2000);
                    //   gtag('event', 'wysłanie formularza', { 'event_category': 'kontakt' });

                  }

                } else { alert(this.status); ajaxFailed(); }

              };
              
              request.onerror = function() { ajaxFailed(); };

            }

            //mechanika - kliknięcie wyslania wiadomości uruchamia polączenie AJAX
            let contactForm = document.querySelector('.p24_form');
            contactForm.addEventListener('submit', sendForm);
            for (i = 0; i < contactForm.length; i++) { 
                contactForm[i].addEventListener('submit', sendForm);
            }

            //system walidacji na żywo - komunikaty podpowiadające
            var reqGroup = document.querySelectorAll('.input_required');
            let element = document.createElement('span'); element.classList.add("valid"); //stworzenie elementu span.valid dla elementów wymaganych
            for(var x=0; x<reqGroup.length; x++) {
                reqGroup[x].querySelector('p').insertAdjacentElement('beforeend', element);
                reqGroup[x].addEventListener('input', function() {
                    console.log(this);
                    let input = this.lastElementChild.value;
                    let spanItem = this.querySelector('.valid');
                    let span = this.firstElementChild.innerHTML;
                    let type = this.dataset.type;
                    spanItem.classList.add("visible");

                    if (type == 'name'){
                        valName(this, 3, input);
                    } else if (type == 'mail') {
                        valMail(this, 4, input);
                    } else if (type == 'phone') {
                        valPhone(this, 9, input);
                    }
                    
                    function valMail(tt, i, input){
                       console.log('mm');
                        if (input.length < i){
                            spanItem.innerHTML = 'pole wymagane';
                        } else {
                            var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
                            let xx = re.test(String(input).toLowerCase());
                            if (xx==true){
                                spanItem.innerHTML = '&#x2713;';
                            } else {
                                spanItem.innerHTML = 'nieprawildowy adres e-mail';
                            }
                        }
                    }

                    function valPhone(tt, i, input){
                        if (input.length < i){
                            tt.firstElementChild.innerHTML = 'pole wymagane';
                        } else {
                            var re = /^[\+]?[(]?[0-9]{3}[)]?[-\s\.]?[0-9]{3}[-\s\.]?[0-9]{3,6}$/im;
                            let xx = re.test(String(input));
                            if (xx==true){
                                tt.firstElementChild.innerHTML = '&#x2713;';
                            } else {
                                tt.firstElementChild.innerHTML = 'nieprawildowy numer telefonu';
                            }
                        }
                    }

                    function valName(tt, i, input) {
                       console.log(spanItem);
                        if (input.length < i && input.length > 0){
                            spanItem.innerHTML = 'min. '+i+' znaki ('+input.length+')';
                        } else if (input.length <= 0) {
                            spanItem.innerHTML = 'pole wymagane';
                        } else {
                            spanItem.innerHTML = '&#x2713;';
                        }
                    }
                }, false);
            }