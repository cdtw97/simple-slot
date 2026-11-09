"""Run the same PHP CLI evaluator and forward its output and exit status."""
from pathlib import Path
import subprocess
import sys

if __name__ == "__main__":
    sys.exit(subprocess.call(["php", str(Path(__file__).with_suffix(".php")), *sys.argv[1:]]))
