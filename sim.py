import random

# Setup the initial state
cost_per_spin = 5
total_spins = 10000  # Adjust the number of spins to simulate here

total_spent = total_spins * cost_per_spin
total_won = 0

# Frequency and payout for each type of win
statistics = {
    "2": {"payout": 0, "occurrences": 0},
    "3": {"payout": 0, "occurrences": 0},
    "4": {"payout": 0, "occurrences": 0},
    "5": {"payout": 0, "occurrences": 0},
    "6": {"payout": 0, "occurrences": 0},
    "diag": {"payout": 0, "occurrences": 0},
    "four_corner": {"payout": 0, "occurrences": 0}
}


def spin():
    symbols = ['🍎', '🍊', '🍋', '🍉', '🍇', '🍒', '🍐', '🍍', '🥥', '🥝', '🍓']  # 11 symbols
    matrix = [['' for _ in range(6)] for _ in range(3)]  # Create 3x6 matrix to represent the reels
    for i in range(3):
        for j in range(6):
            matrix[i][j] = random.choice(symbols)  # Assign random symbols to reels
    return matrix


def check_for_line_win(matrix):
    winning_lines = []
    symbols = ['🍎', '🍊', '🍋', '🍉', '🍇', '🍒', '🍐', '🍍', '🥥', '🥝', '🍓']

    for row in matrix:
        current_symbol = row[0]  # start with the first symbol
        current_length = 1  # we have at least one symbol

        for column in range(1, len(row)):  # check rest of the row
            if row[column] == current_symbol:  # if symbol is the same as the current one, increase length
                current_length += 1
            else:  # if symbol is different, check if we had a win before, then reset
                if current_length in range(2, 7) and current_symbol in symbols:
                    winning_lines.append({"row": matrix.index(row), "start": column - current_length, "end": column - 1, "length": current_length, "symbol": current_symbol})
                current_symbol = row[column]
                current_length = 1

        # check for a winning line at the end of the row
        if current_length in range(2, 7) and current_symbol in symbols:
            winning_lines.append({"row": matrix.index(row), "start": len(row) - current_length, "end": len(row) - 1, "length": current_length, "symbol": current_symbol})

    return winning_lines


def check_for_diagonal_win(matrix):
    winning_lines = []
    directions = [(1, 1), (1, -1)]  # Directions to check: (1, 1) for diagonals from top-left to bottom-right, (1, -1) for diagonals from top-right to bottom-left

    for direction in directions:
        dx, dy = direction
        for x in range(len(matrix[0])):
            first_symbol = matrix[0][x]  # Take the first symbol of the line
            is_winning_line = True

            for i in range(1, len(matrix)):
                y = x + i * dy
                # If the y position is out of bounds or the symbol doesn't match the first one, it's not a winning line
                if y < 0 or y >= len(matrix[i]) or matrix[i][y] != first_symbol:
                    is_winning_line = False
                    break

            # If all symbols in the line are the same, it's a winning line
            if is_winning_line:
                winning_lines.append({"start": (0, x), "end": (2, x + 2 * dy), "length": 3, "symbol": first_symbol})

    return winning_lines


def check_four_corners(matrix):
    winning_lines = []
    corners = [(0, 0), (0, 5), (2, 0), (2, 5)]  # Positions of the four corners

    first_symbol = matrix[corners[0][0]][corners[0][1]]  # Take the symbol of the first corner
    is_winning_line = True

    for corner in corners:
        # If a corner's symbol doesn't match the first one, it's not a winning line
        if matrix[corner[0]][corner[1]] != first_symbol:
            is_winning_line = False
            break

    # If all corners have the same symbol, it's a winning line
    if is_winning_line:
        winning_lines.append({"corners": corners, "length": 4, "symbol": first_symbol})

    return winning_lines


def calculate_payout(line_wins, diagonal_wins, four_corner_wins, cost_per_spin):
    payouts = {2: 0.75215, 3: 0.72, 4: 1.02, 5: 1.28, 6: 10, "diagonal": 0.72, "four_corner": 0.72}
    total_payout = 0
    payout_details = []

    # Analyze line wins
    for line_win in line_wins:
        payout = cost_per_spin * payouts[line_win["length"]]
        total_payout += payout
        payout_details.append({"type": "line", "symbol": line_win["symbol"], "length": line_win["length"], "payout": payout})

    # Analyze diagonal wins
    for diagonal_win in diagonal_wins:
        payout = cost_per_spin * payouts["diagonal"]
        total_payout += payout
        payout_details.append({"type": "diagonal", "symbol": diagonal_win["symbol"], "length": diagonal_win["length"], "payout": payout})

    # Analyze four corner wins
    for four_corner_win in four_corner_wins:
        payout = cost_per_spin * payouts["four_corner"]
        total_payout += payout
        payout_details.append({"type": "four_corner", "symbol": four_corner_win["symbol"], "length": four_corner_win["length"], "payout": payout})

    # Return total payout and details
    return {"totalPayout": total_payout, "payoutDetails": payout_details}


# Main simulation loop
for _ in range(total_spins):
    matrix_3x6 = spin()
    line_wins = check_for_line_win(matrix_3x6)
    diagonal_wins = check_for_diagonal_win(matrix_3x6)
    four_corner_wins = check_four_corners(matrix_3x6)

    payout = calculate_payout(line_wins, diagonal_wins, four_corner_wins, cost_per_spin)
    if payout["payoutDetails"]:
        for payout_detail in payout["payoutDetails"]:
            # Check for a line win
            if payout_detail["type"] == "line":
                # Determine which type of line was won
                length = payout_detail["length"]
                if length == 2:
                    statistics["2"]["payout"] += payout_detail["payout"]
                    statistics["2"]["occurrences"] += 1
                elif length == 3:
                    statistics["3"]["payout"] += payout_detail["payout"]
                    statistics["3"]["occurrences"] += 1
                elif length == 4:
                    statistics["4"]["payout"] += payout_detail["payout"]
                    statistics["4"]["occurrences"] += 1
                elif length == 5:
                    statistics["5"]["payout"] += payout_detail["payout"]
                    statistics["5"]["occurrences"] += 1
                elif length == 6:
                    statistics["6"]["payout"] += payout_detail["payout"]
                    statistics["6"]["occurrences"] += 1

            # Check for a diagonal win
            if payout_detail["type"] == "diagonal":
                statistics["diag"]["payout"] += payout_detail["payout"]
                statistics["diag"]["occurrences"] += 1

            # Check for a four corner win
            if payout_detail["type"] == "four_corner":
                statistics["four_corner"]["payout"] += payout_detail["payout"]
                statistics["four_corner"]["occurrences"] += 1

# Output results
print("Payout Table:")
total_won = 0
for key, value in statistics.items():
    print(f"Type: {key}  Payout: {value['payout']:.2f}  Occurrences: {value['occurrences']:.1f}")
    total_won += value["payout"]

print("\nWin Probabilities:")
for key, value in statistics.items():
    print(f"Type: {key}  Probability: {value['occurrences'] / total_spins * 100:.5f}%")

print(f"\nTotal Spent: {total_spent:.2f}")
print(f"Total Won: {total_won:.2f}")
print(f"Payout Percentage: {total_won / total_spent * 100:.6f}")
print(f"Percentage Difference: {(total_won / total_spent * 100) - 100:.6f}")
