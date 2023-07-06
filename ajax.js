// Symbols
const symbols = ['🍎', '🍊', '🍋', '🍉', '🍇', '🍒', '🍐', '🍍', '🥥', '🥝', '🍓'];

document.getElementById('spin-button').addEventListener('click', function() {
    fetch('game.php', {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        clearHighlighting()
        console.log(data);
        let matrix = data.matrix;
        let index = 0;

        for (let reel in matrix) {
            let ul = document.getElementById(reel).querySelector('.reel');

            ul.innerHTML = "";

            let extendedArray = [];
            for (let i = 0; i < 20; i++) {
                extendedArray.push(symbols[Math.floor(Math.random()*symbols.length)]);
            }
            extendedArray = [...extendedArray, ...matrix[reel]];

            extendedArray.forEach(symbol => {
                let li = document.createElement('li');
                li.innerText = symbol;
                ul.appendChild(li);
            });

            // Set initial position of reel to simulate spinning from somewhere in the middle
            gsap.set(ul, {y: -61.2 * 10}); // start from the 10th symbol of the extended array

            // Animate the reel
            gsap.to(ul, {
                y: -61.2 * 20 + 10, // Stop at the 20th symbol (end of random symbols) and adjust ending position
                duration: 1 + 0.2 * index, // Add to duration based on reel index
                ease: "power1.out",
                onComplete: () => {
                  
                        console.log('hi');
                        highlightWinningSymbols(data.lineWins);
                    
                        displayPayoutDetails(data.payout.payoutDetails);
                        updateTokenAmount(data.tokens);
                    
                }
            });

            index++;
        }
    })
    .catch((error) => {
        console.error('Error:', error);
    });
});

function displayPayoutDetails(payoutDetails) {
    const payoutElement = document.getElementById('winning-lines');
    payoutElement.innerHTML = "";

    if (payoutDetails.length === 0) {
      const li = document.createElement('li');
      li.classList.add('list-group-item');
      li.innerText = "No payouts";
      payoutElement.appendChild(li);
    } else {
      payoutDetails.forEach(payout => {
        const li = document.createElement('li');
        li.classList.add('list-group-item');
        li.innerText = `Type: ${payout.type}, Symbol: ${payout.symbol}, Length: ${payout.length}, Payout: ${payout.payout}`;
        payoutElement.appendChild(li);
      });
    }
}
  
  function updateTokenAmount(tokens) {
    const tokenAmount = document.getElementById('token-amount');
    tokenAmount.innerText = tokens.toFixed(2);
  }

  function highlightWinningSymbols(lineWins) {
    lineWins.forEach(line => {
        for (let reel = line.start; reel <= line.end; reel++) {
            let ul = document.getElementById(`reel${reel + 1}`).querySelector('.reel');
            let winningSymbol = ul.children[ul.children.length - 3 + line.row];
            winningSymbol.classList.add('highlight');
        }
    });
}

function highlightDiagonalWins(diagonalWins) {
  diagonalWins.forEach(win => {
      // Figure out the direction of the diagonal
      let isTopLeftToBottomRight = (win.start[0] < win.end[0] && win.start[1] < win.end[1]);
      
      for (let reel = 0; reel < 6; reel++) {
          let ul = document.getElementById(`reel${reel + 1}`).querySelector('.reel');
          // Use the 'isTopLeftToBottomRight' variable to decide which symbol to highlight
          let position = isTopLeftToBottomRight ? reel : 2 - reel;
          let winningSymbol = ul.children[ul.children.length - 3 + position];
          winningSymbol.classList.add('highlight');
      }
  });
}

function highlightCornerWins(cornerWins) {
  if(cornerWins.length > 0) {
      let reels = ["reel1", "reel6"];
      let positions = [0, 2];

      reels.forEach(reel => {
          positions.forEach(position => {
              let ul = document.getElementById(reel).querySelector('.reel');
              let winningSymbol = ul.children[ul.children.length - 3 + position];
              winningSymbol.classList.add('highlight');
          });
      });
  }
}
function clearHighlighting() {
  let highlightedSymbols = document.querySelectorAll('.highlight');
  highlightedSymbols.forEach(symbol => symbol.classList.remove('highlight'));
}



