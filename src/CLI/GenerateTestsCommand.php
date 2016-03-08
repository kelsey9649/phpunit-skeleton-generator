<?php
/**
 * phpunit-skeleton-generator
 *
 *
 * @author    Curtis Kelsey <curtis.kelsey@gmail.com>
 * @license   http://www.opensource.org/licenses/BSD-3-Clause  The BSD 3-Clause License
 */
namespace SebastianBergmann\PHPUnit\SkeletonGenerator\CLI;

use SebastianBergmann\PHPUnit\SkeletonGenerator\AbstractGenerator;
use SebastianBergmann\PHPUnit\SkeletonGenerator\TestGenerator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
 
Class GenerateTestsCommand extends Command
{
    private $root;
    
    private $sourceCodePath;
    
    private $testCodePath;
    
    private $verbose = false;
    
    /**
     * Configures the current command.
     */
    protected function configure()
    {
        $this->setName('generate-tests')
             ->setDescription('Generates tests for all files within a directory')
             ->addArgument(
                 'source-path',
                 InputArgument::OPTIONAL,
                 'The directory that the source code is stored in'
             )
             ->addArgument(
                 'test-path',
                 InputArgument::OPTIONAL,
                 'The directory that the test code is stored in'
             )->addOption(
                'bootstrap',
                null,
                InputOption::VALUE_REQUIRED,
                'A "bootstrap" PHP file that is run at startup'
            );

        parent::configure();
    }
    
    public function execute(InputInterface $input, OutputInterface $output)
    {
        if ($input->getOption('bootstrap') && file_exists($input->getOption('bootstrap'))) {
            
            include $input->getOption('bootstrap');
        }

        // Default root path
        $this->root = getcwd();
        print(($output->isVerbose()) ? "Current Working Directory: {$this->root}\n" : '');

        if ($input->getArgument('source-path')) {
            
            $this->sourceCodePath = rtrim($input->getArgument('source-path'), '/');
            
        } else {

            // Default source path
            $this->sourceCodePath = $this->root."/src";
        }
        
        print($output->isVerbose() ? "Source Code Path: {$this->sourceCodePath}\n" : '');

        
        if ($input->getArgument('test-path')) {
            
            $this->testCodePath = rtrim($input->getArgument('test-path'), '/');
            
        } else {

            // Default test path
            $this->testCodePath = $this->root."/tests";
        }
        
        print($output->isVerbose() ? "Test Code Path: {$this->testCodePath}\n" : '');

        // Check for a source code directory to generate tests from
        if (!file_exists($this->sourceCodePath)) {
            
            print("There is not a source code directory located at $this->sourceCodePath.");
            return;    
        }
        
        // Check for the test directory we will store tests in
        if (!file_exists($this->testCodePath)) {
            
            mkdir($this->testCodePath);
        }
        
        $this->descendDirectory($this->sourceCodePath, $output);

        echo "Done.\n";
    }

    /**
     * TODO do not create empty directories
     * @param $directory
     * @param $output
     */
    private function descendDirectory($directory, &$output)
    {
        print(($output->isVerbose()) ? "Scanning {$directory}...\n" : '');
        
        // If it is not a directory stop here
        if (!is_dir($directory)) {
            
            return; 
        }
        
        //Grab the relative path
        $relativePath = substr($directory, strlen($this->sourceCodePath));
        print(($output->isVerbose()) ? "Relative path: {$relativePath}\n" : '');

        $namespaceRelativePath = str_replace("/", "\\", $relativePath);
        print(($output->isVerbose()) ? "Namespace relative path: {$namespaceRelativePath}\n" : '');

        if (!file_exists($this->testCodePath.$relativePath)) {

            mkdir($this->testCodePath.$relativePath);
        }
        
        // Grab all of the directory's children
        $children = scandir($directory);
        
        foreach ($children as $child) {

            // Don't traverse the pointers
            if ($child=='.' || $child=='..') {
                
                continue;
            }
            
            // If the child is a directory
            if (is_dir($directory.'/'.$child)) {
                
                // And does not exist in the test code path
                if (!file_exists($this->testCodePath.$relativePath.'/'.$child)) {
                    
                    // Create it
                    if ($output->isVerbose()) {
                        
                        echo "Creating {$relativePath}/{$child} in the test code path...\n";
                    }
                    
                    mkdir($this->testCodePath.$relativePath.'/'.$child);
                }  
                
                $this->descendDirectory($directory.'/'.$child, $output);
            }  

            $childFileName = $directory.'/'.$child;

            // If the child is a file
            if (is_file($childFileName)) {
                
                $nameParts = explode(".",$child);

                if ($nameParts[1] !== 'php') {
                    continue;
                }

                $filename = $nameParts[0].'Test.'.$nameParts[1];
                
                // And does not exist in the test code path
                if (!file_exists($this->testCodePath.$relativePath.'/'.$filename)) {
                    
                    // Create it
                    if ($output->isVerbose()) {
                        
                        echo "Creating {$relativePath}/{$filename} in the test code path...\n";
                    }

                    $newFile = $this->testCodePath;
                    $newFile .= ($relativePath != '') ? $relativePath.'/' : '/';
                    $newFile .= $filename;

                    file_put_contents($newFile, '');

                    $srcContent = file_get_contents($childFileName);
                    preg_match('/namespace\s([a-zA-Z\\\]+)/', $srcContent, $matches);

                    $namespace = '';
                    if (count($matches) > 1) {
                        $namespace = $matches[1];
                    }

                    // Let's try generating the test file
                    $input['class'] = $namespace."\\".basename($child,'.php');
                    $input['class-source'] = $childFileName;
                    $input['test-class'] = $namespace."\\".basename($filename,'.php');
                    $input['test-source'] = $this->testCodePath.$relativePath.'/'.$filename;

                    $generator = $this->getGenerator($input);

                    $generator->write();

                    if ($output->isVerbose()) {

                        $output->writeln(
                            sprintf(
                                "Wrote skeleton for \"%s\" to \"%s\".",
                                $generator->getOutClassName(),
                                $generator->getOutSourceFile()
                            )
                        );
                    }
                }
            }  
        }   
    }

    /**
     * @param InputInterface  $input  An InputInterface instance
     * @return AbstractGenerator
     */
    protected function getGenerator($input)
    {
        return new TestGenerator(
            $input['class'],
            $input['class-source'],
            $input['test-class'],
            $input['test-source']
        );
    }
}




