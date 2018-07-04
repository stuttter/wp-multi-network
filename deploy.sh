#!/bin/bash

# main config
PLUGINSLUG="wp-multi-network"
CURRENTDIR=`pwd`
MAINFILE="wpmn-loader.php"
DEFAULT_EDITOR="/usr/bin/vim"

# git config
GITPATH="$CURRENTDIR/" # this file should be in the base of your git repository

# svn config
SVNPATH="/tmp/$PLUGINSLUG" # Path to a temp SVN repo. No trailing slash required.
SVNURL="https://plugins.svn.wordpress.org/$PLUGINSLUG/" # Remote SVN repo on wordpress.org

# Let's begin...
echo
echo "Deploy WordPress plugin"
echo "======================="
echo

# Check version in readme.txt is the same as plugin file after translating both to unix
# line breaks to work around grep's failure to identify mac line breaks
NEWVERSION1=`grep "^Stable tag:" "$GITPATH/readme.txt" | awk -F' ' '{print $NF}'`
echo "readme.txt version: $NEWVERSION1"
NEWVERSION2=`grep "Version: " "$GITPATH/$MAINFILE" | awk -F' ' '{print $NF}'`
echo "$MAINFILE version: $NEWVERSION2"

if [ "$NEWVERSION1" != "$NEWVERSION2" ]
	then echo "Version in readme.txt & $MAINFILE don't match. Exiting."
	exit 1
fi

echo "Versions match in readme.txt and $MAINFILE. Let's proceed..."

if git show-ref --quiet --tags --verify -- "refs/tags/$NEWVERSION1"
	then
		echo "Version $NEWVERSION1 already exists as git tag. Skipping."
	else
		printf "Tagging new Git version..."
		git tag -a "$NEWVERSION1" -m "tagged version $NEWVERSION1"
		echo "Done."

		printf "Pushing new Git tag..."
		git push --quiet --tags
		echo "Done."
fi

cd $GITPATH

printf "Creating local copy of SVN repo..."
svn checkout --quiet $SVNURL/trunk $SVNPATH/trunk
echo "Done."

printf "Exporting the HEAD of master from Git to the trunk of SVN..."
git checkout-index --quiet --all --force --prefix=$SVNPATH/trunk/
echo "Done."

printf "Preparing commit message..."
echo "updated version to $NEWVERSION1" > /tmp/wppdcommitmsg.tmp
echo "Done."

printf "Preparing assets-wp-repo..."
if [ -d $SVNPATH/trunk/assets-wp-repo ]
	then
		svn checkout --quiet $SVNURL/assets $SVNPATH/assets > /dev/null 2>&1
		mkdir $SVNPATH/assets/ > /dev/null 2>&1 # Create assets directory if it doesn't exists
		mv $SVNPATH/trunk/assets-wp-repo/* $SVNPATH/assets/ # Move new assets
		rm -rf $SVNPATH/trunk/assets-wp-repo # Clean up
		cd $SVNPATH/assets/ # Switch to assets directory
		svn stat | grep "^?\|^M" > /dev/null 2>&1 # Check if new or updated assets exists
		if [ $? -eq 0 ]
			then
				svn stat | grep "^?" | awk '{print $2}' | xargs svn add --quiet # Add new assets
				echo -en "Committing new assets..."
				svn commit --quiet -m "updated assets"
				echo "Done."
			else
				echo "Unchanged."
		fi
	else
		echo "No assets exists."
fi

cd $SVNPATH/trunk/

printf "Removing unnecessary GitHub and test files..."
rm LICENSE.md
rm README.md
rm -rf tests
echo "Done."

printf "Ignoring dev files..."
svn propset --quiet svn:ignore ".editorconfig
.git
.gitattributes
.gitignore
.travis.yml
composer.json
composer.lock
deploy.sh
phpcs.xml.dist
phpunit.xml.dist" .
echo "Done."

printf "Adding new files..."
svn stat | grep "^?" | awk '{print $2}' | xargs svn add --quiet
echo "Done."

printf "Removing old files..."
svn stat | grep "^\!" | awk '{print $2}' | xargs svn remove --quiet
echo "Done."

printf "Enter a commit message for this new SVN version..."
$DEFAULT_EDITOR /tmp/wppdcommitmsg.tmp
COMMITMSG=`cat /tmp/wppdcommitmsg.tmp`
rm /tmp/wppdcommitmsg.tmp
echo "Done."

printf "Committing new SVN version..."
svn commit --quiet -m "$COMMITMSG"
echo "Done."

printf "Tagging and committing new SVN tag..."
svn copy $SVNURL/trunk $SVNURL/tags/$NEWVERSION1 --quiet -m "tagged version $NEWVERSION1"
echo "Done."

printf "Removing temporary directory %s..." "$SVNPATH"
rm -rf $SVNPATH/
echo "Done."

echo
echo "Plugin $PLUGINSLUG version $NEWVERSION1 has been successfully deployed."
echo
