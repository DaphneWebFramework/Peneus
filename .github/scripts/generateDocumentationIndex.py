"""
generateDocumentationIndex.py

(C) 2024 by Eylem Ugurel

Licensed under a Creative Commons Attribution 4.0 International License.

You should have received a copy of the license along with this work. If not,
see <http://creativecommons.org/licenses/by/4.0/>.

Description:
  This script generates a structured README.md file based on a directory's
  structure. It recursively finds all Markdown (.md) files and organizes them
  into a hierarchical index, reflecting the directory structure.

Usage:
  python generateDocumentationIndex.py [directory]
"""

import os, sys, argparse

class IndexGenerator:
  """
  Responsible for generating a Markdown documentation index based on a
  directory structure. It traverses directories, filters out unnecessary
  files, and creates a structured index in the form of a README.md file.

  IndexGenerator
  ├── __init__
  ├── generate
  │   ├── _filterDirectory
  │   ├── _processDirectory
  │   │   ├── _addDirectoryHeadings
  │   │   └── _generateLink
  │   └── _writeToFile
  """

  def __init__(self, rootDirectory, outputFile):
    """
    Initializes the IndexGenerator with the root directory and the output file.
    Verifies that the specified root directory exists.

    Parameters:
    - rootDirectory (str): The root directory to start the file traversal.
    - outputFile (str): The file to write the generated documentation.
    """
    if not os.path.exists(rootDirectory):
      print(f"Error: The specified directory '{rootDirectory}' does not exist.")
      sys.exit(1)
    self.rootDirectory = rootDirectory
    self.outputFile = outputFile
    self.contents = f'# {os.path.basename(os.path.normpath(rootDirectory))}\n'
    self.printed = set()  # Tracks directories that have already been printed

  def generate(self):
    """
    Traverses the directory structure, filtering files and directories, and
    generates the Markdown index. The final result is written to the output file.
    """
    for directory, subdirectories, files in os.walk(self.rootDirectory):
      if self._filterDirectory(subdirectories, files):
        self._processDirectory(directory, files)
    self._writeToFile()

  def _filterDirectory(self, subdirectories, files):
    """
    Filters out unnecessary directories and files, such as `.git`, `.github`,
    and `README.md`. This method modifies the subdirectories and files in place.

    Parameters:
    - subdirectories (list): A list of subdirectories in the current directory.
    - files (list): A list of files in the current directory.

    Returns:
      bool: True if there are valid Markdown files to process, False otherwise.
    """
    subdirectories[:] = [
      subdirectory
      for subdirectory in subdirectories
      if subdirectory not in ['.git', '.github']
    ]
    files[:] = [
      file
      for file in files
      if file.endswith('.md') and file != 'README.md'
    ]
    return bool(files)

  def _processDirectory(self, directory, files):
    """
    Processes the current directory, adding directory headings and file links to
    the Markdown index.

    Parameters:
    - directory (str): The current directory being processed.
    - files (list): A list of files in the current directory.
    """
    relativeDirectory = os.path.relpath(directory, self.rootDirectory)
    if relativeDirectory != '.':  # Only add headings for non-root directories
      self._addDirectoryHeadings(relativeDirectory)
    for file in sorted(files):
      if relativeDirectory != '.':  # If not in root, prepend relative directory
        file = os.path.join(relativeDirectory, file)
      self.contents += self._generateLink(file) + '\n'

  def _addDirectoryHeadings(self, relativeDirectory):
    """
    Adds hierarchical headings to the Markdown index based on the directory
    structure. The depth of the directory determines the heading level.

    Parameters:
    - relativeDirectory (str): The relative directory path to add headings for.
    """
    parts = relativeDirectory.split(os.sep)
    accumulated = []  # Used to build the full path incrementally
    for i, part in enumerate(parts):
      accumulated.append(part)
      key = '/'.join(accumulated)  # Build the full path (namespace)
      if key not in self.printed:  # Only add heading if not already printed
        headingLevel = '#' * (i + 2)
        self.contents += f'\n{headingLevel} {part}\n'
        self.printed.add(key)

  def _generateLink(self, file):
    """
    Generates a Markdown link for a given file.

    Parameters:
    - file (str): The file path.

    Returns:
      str: A Markdown-formatted link with the file name as the link text and the
           file path as the link target.
    """
    name = os.path.basename(file)
    title = os.path.splitext(name)[0]
    link = file.replace(os.sep, '/')
    return f'- [{title}]({link})'

  def _writeToFile(self):
    """
    Writes the accumulated Markdown index to the output file, including a
    simple footer.
    """
    with open(self.outputFile, 'w') as f:
      f.write(self.contents)
      f.write('\n---\n\n*This documentation index was automatically generated.*\n')

if __name__ == '__main__':
  parser = argparse.ArgumentParser(
    description=(
      'Recursively finds all Markdown (.md) files in a specified directory and '
      'generates a structured README.md file based on the directory contents.'
    )
  )
  parser.add_argument(
    'directory',
    nargs='?',
    default='.',
    help=(
      'The root directory to start the traversal. Defaults to the current '
      'directory.'
    )
  )
  args = parser.parse_args()
  rootDirectory = os.path.abspath(args.directory)
  outputFile = os.path.join(rootDirectory, 'README.md')
  generator = IndexGenerator(rootDirectory, outputFile)
  generator.generate()
