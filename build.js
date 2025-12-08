#!/usr/bin/env node

const { execSync, spawn } = require('child_process');
const { existsSync, readdirSync } = require('fs');
const readline = require('readline');

const rl = readline.createInterface({
  input: process.stdin,
  output: process.stdout
});

function question(prompt) {
  return new Promise((resolve) => {
    rl.question(prompt, resolve);
  });
}

function execCommand(command) {
  try {
    return execSync(command, { encoding: 'utf8' }).trim();
  } catch (error) {
    return null;
  }
}

function findPHPVersions() {
  const phpVersions = [];
  const seen = new Set();
  const locations = [];
  
  // First, check what's available via Homebrew
  const brewPrefixes = ['/opt/homebrew', '/usr/local'];
  for (const prefix of brewPrefixes) {
    // Check Cellar directory directly for all PHP installations
    const cellarDir = `${prefix}/Cellar`;
    if (existsSync(cellarDir)) {
      try {
        const entries = readdirSync(cellarDir);
        const phpDirs = entries.filter(name => name.startsWith('php'));
        
        phpDirs.forEach(formula => {
          // First try opt symlink (faster and cleaner)
          const optPath = `${prefix}/opt/${formula}/bin/php`;
          const optPhpizePath = `${prefix}/opt/${formula}/bin/phpize`;
          const optPhpConfigPath = `${prefix}/opt/${formula}/bin/php-config`;
          
          if (existsSync(optPath) && existsSync(optPhpizePath)) {
            locations.push({
              php: optPath,
              phpize: optPhpizePath,
              phpConfig: optPhpConfigPath
            });
          } else {
            // Fall back to Cellar path
            const cellarBase = `${cellarDir}/${formula}`;
            if (existsSync(cellarBase)) {
              try {
                const versions = readdirSync(cellarBase);
                versions.forEach(version => {
                  const cellarPath = `${cellarBase}/${version}/bin/php`;
                  const cellarPhpizePath = `${cellarBase}/${version}/bin/phpize`;
                  const cellarPhpConfigPath = `${cellarBase}/${version}/bin/php-config`;
                  
                  if (existsSync(cellarPath) && existsSync(cellarPhpizePath)) {
                    locations.push({
                      php: cellarPath,
                      phpize: cellarPhpizePath,
                      phpConfig: cellarPhpConfigPath
                    });
                  }
                });
              } catch (e) {
                // Ignore errors reading version directory
              }
            }
          }
        });
      } catch (e) {
        // Ignore errors reading Cellar directory
      }
    }
    
    // Also check symlinks in bin directory
    const binPhp = `${prefix}/bin/php`;
    if (existsSync(binPhp)) {
      const binPhpize = `${prefix}/bin/phpize`;
      const binPhpConfig = `${prefix}/bin/php-config`;
      if (existsSync(binPhpize)) {
        locations.push({
          php: binPhp,
          phpize: binPhpize,
          phpConfig: binPhpConfig
        });
      }
    }
  }
  
  // Check PATH
  const pathPhp = execCommand('which php');
  if (pathPhp) {
    const pathPhpize = execCommand('which phpize');
    const pathPhpConfig = execCommand('which php-config');
    if (pathPhpize) {
      locations.unshift({
        php: pathPhp,
        phpize: pathPhpize,
        phpConfig: pathPhpConfig
      });
    }
  }
  
  // Check common system locations
  ['/usr/bin/php', '/usr/local/bin/php'].forEach(php => {
    const phpize = php.replace('/php', '/phpize');
    const phpConfig = php.replace('/php', '/php-config');
    if (existsSync(php) && existsSync(phpize)) {
      locations.push({
        php: php,
        phpize: phpize,
        phpConfig: phpConfig
      });
    }
  });
  
  // Process all locations and extract version info
  for (const loc of locations) {
    const version = execCommand(`${loc.php} -v 2>&1 | head -n 1`);
    if (version && version.includes('PHP')) {
      const match = version.match(/PHP (\d+\.\d+\.\d+)/);
      if (match) {
        const versionNum = match[1];
        const resolvedPath = execCommand(`readlink -f ${loc.php} 2>/dev/null`) || loc.php;
        const key = `${resolvedPath}:${versionNum}`;
        
        if (!seen.has(key)) {
          seen.add(key);
          
          // Determine if it's from Homebrew
          const isHomebrew = loc.php.includes('/opt/homebrew/') || loc.php.includes('/usr/local/opt/');
          const source = isHomebrew ? 'Homebrew' : 'System';
          
          phpVersions.push({
            path: loc.php,
            phpize: loc.phpize,
            phpConfig: loc.phpConfig,
            version: versionNum,
            display: `PHP ${versionNum} [${source}] (${loc.php})`
          });
        }
      }
    }
  }
  
  // Sort by version number (newest first)
  phpVersions.sort((a, b) => {
    const [aMaj, aMin, aPatch] = a.version.split('.').map(Number);
    const [bMaj, bMin, bPatch] = b.version.split('.').map(Number);
    
    if (aMaj !== bMaj) return bMaj - aMaj;
    if (aMin !== bMin) return bMin - aMin;
    return bPatch - aPatch;
  });
  
  return phpVersions;
}

function findV8Versions() {
  const v8Versions = [];
  const seen = new Set();
  
  // Check Homebrew installations
  const brewPrefixes = ['/opt/homebrew', '/usr/local'];
  
  for (const prefix of brewPrefixes) {
    // Find all V8 formulae installed by Homebrew
    const brewList = execCommand(`${prefix}/bin/brew list --formula 2>/dev/null | grep "^v8"`);
    if (brewList) {
      brewList.split('\n').forEach(formula => {
        formula = formula.trim();
        if (formula === 'v8' || formula.startsWith('v8@')) {
          const v8Path = `${prefix}/opt/${formula}`;
          if (existsSync(v8Path)) {
            const version = getV8Version(v8Path, prefix);
            const key = `${v8Path}:${version}`;
            
            if (!seen.has(key)) {
              seen.add(key);
              v8Versions.push({
                path: v8Path,
                version: version,
                display: `V8 ${version} [Homebrew: ${formula}] (${v8Path})`
              });
            }
          }
        }
      });
    }
    
    // Also check standard locations even if not in brew list
    const standardPaths = [
      `${prefix}/opt/v8`,
      `${prefix}/Cellar/v8`
    ];
    
    for (const path of standardPaths) {
      if (existsSync(path)) {
        const version = getV8Version(path, prefix);
        const key = `${path}:${version}`;
        
        if (!seen.has(key)) {
          seen.add(key);
          v8Versions.push({
            path: path,
            version: version,
            display: `V8 ${version} [Homebrew] (${path})`
          });
        }
      }
    }
  }
  
  // Sort by version number (newest first)
  v8Versions.sort((a, b) => {
    const aParts = a.version.split('.').map(Number);
    const bParts = b.version.split('.').map(Number);
    
    for (let i = 0; i < Math.max(aParts.length, bParts.length); i++) {
      const aVal = aParts[i] || 0;
      const bVal = bParts[i] || 0;
      if (aVal !== bVal) return bVal - aVal;
    }
    return 0;
  });
  
  return v8Versions;
}

function getV8Version(v8Path, brewPrefix) {
  // Try multiple methods to get V8 version
  
  // Method 1: Check if this is a symlink to a Cellar version
  const realPath = execCommand(`readlink ${v8Path}`);
  if (realPath) {
    const cellarMatch = realPath.match(/Cellar\/v8[^\/]*\/([^\/]+)/);
    if (cellarMatch) {
      return cellarMatch[1];
    }
  }
  
  // Method 2: Use brew info to get version
  const formula = v8Path.split('/').pop(); // Get formula name from path
  const brewInfo = execCommand(`${brewPrefix}/bin/brew info ${formula} --json 2>/dev/null`);
  if (brewInfo) {
    try {
      const info = JSON.parse(brewInfo);
      if (info && info[0] && info[0].installed && info[0].installed[0]) {
        return info[0].installed[0].version;
      }
      if (info && info[0] && info[0].versions && info[0].versions.stable) {
        return info[0].versions.stable;
      }
    } catch (e) {
      // Ignore JSON parse errors
    }
  }
  
  // Method 3: Try pkg-config
  const pkgConfigVersion = execCommand(`PKG_CONFIG_PATH=${v8Path}/lib/pkgconfig:${v8Path}/libexec/lib/pkgconfig pkg-config --modversion v8 2>/dev/null`);
  if (pkgConfigVersion) {
    return pkgConfigVersion;
  }
  
  // Method 4: Check include/v8-version.h
  const versionHeader = `${v8Path}/include/v8-version.h`;
  if (existsSync(versionHeader)) {
    const headerContent = execCommand(`cat ${versionHeader}`);
    if (headerContent) {
      const major = headerContent.match(/#define V8_MAJOR_VERSION (\d+)/);
      const minor = headerContent.match(/#define V8_MINOR_VERSION (\d+)/);
      const build = headerContent.match(/#define V8_BUILD_NUMBER (\d+)/);
      const patch = headerContent.match(/#define V8_PATCH_LEVEL (\d+)/);
      
      if (major && minor && build) {
        let version = `${major[1]}.${minor[1]}.${build[1]}`;
        if (patch && patch[1] !== '0') {
          version += `.${patch[1]}`;
        }
        return version;
      }
    }
  }
  
  // Method 5: Check libv8.so version
  const libPath = `${v8Path}/lib`;
  if (existsSync(libPath)) {
    const libs = execCommand(`ls ${libPath}/libv8*.dylib 2>/dev/null || ls ${libPath}/libv8*.so 2>/dev/null`);
    if (libs) {
      const versionMatch = libs.match(/libv8[^\/]*\.(\d+\.\d+\.\d+)/);
      if (versionMatch) {
        return versionMatch[1];
      }
    }
  }
  
  return 'unknown';
}


function runCommand(command, args = [], options = {}) {
  return new Promise((resolve, reject) => {
    console.log(`\n> ${command} ${args.join(' ')}`);
    const proc = spawn(command, args, {
      stdio: 'inherit',
      shell: true,
      ...options
    });
    
    proc.on('close', (code) => {
      if (code === 0) {
        resolve();
      } else {
        reject(new Error(`Command failed with exit code ${code}`));
      }
    });
    
    proc.on('error', reject);
  });
}

(async function main() {
  console.log('🔍 Scanning for PHP and V8 installations...\n');
  
  const phpVersions = findPHPVersions();
  const v8Versions = findV8Versions();
  
  if (phpVersions.length === 0) {
    console.error('❌ No PHP installations found!');
    process.exit(1);
  }
  
  if (v8Versions.length === 0) {
    console.error('❌ No V8 installations found!');
    console.error('   Install V8 with: brew install v8');
    process.exit(1);
  }
  
  console.log('Found PHP versions:');
  phpVersions.forEach((php, i) => {
    console.log(`  ${i + 1}. ${php.display}`);
  });
  
  console.log('\nFound V8 versions:');
  v8Versions.forEach((v8, i) => {
    console.log(`  ${i + 1}. ${v8.display}`);
  });
  
  console.log('');
  
  let phpChoice = await question(`Select PHP version (1-${phpVersions.length}): `);
  phpChoice = parseInt(phpChoice) - 1;
  
  if (phpChoice < 0 || phpChoice >= phpVersions.length) {
    console.error('Invalid selection');
    process.exit(1);
  }
  
  let v8Choice = await question(`Select V8 version (1-${v8Versions.length}): `);
  v8Choice = parseInt(v8Choice) - 1;
  
  if (v8Choice < 0 || v8Choice >= v8Versions.length) {
    console.error('Invalid selection');
    process.exit(1);
  }
  
  rl.close();
  
  const selectedPhp = phpVersions[phpChoice];
  const selectedV8 = v8Versions[v8Choice];
  
  console.log(`\n✅ Selected PHP: ${selectedPhp.display}`);
  console.log(`✅ Selected V8: ${selectedV8.display}`);
  
  try {
    console.log('\n📦 Building v8js extension...\n');
    
    // Step 1: phpize --clean
    console.log('Step 1/4: Cleaning...');
    await runCommand(selectedPhp.phpize, ['--clean']);
    
    // Step 2: phpize
    console.log('\nStep 2/4: Running phpize...');
    await runCommand(selectedPhp.phpize);
    
    // Step 3: configure
    console.log('\nStep 3/4: Configuring...');
    const configureArgs = [
      `--with-v8js=${selectedV8.path}`,
      `--with-php-config=${selectedPhp.phpConfig}`,
      'CPPFLAGS="-DV8_COMPRESS_POINTERS -DV8_ENABLE_SANDBOX"'
    ];
    await runCommand('./configure', configureArgs);
    
    // Step 4: make
    console.log('\nStep 4/4: Compiling...');
    await runCommand('make', ['-j4']);
    
    console.log('\n✨ Build completed successfully!');
    console.log('\nTo test: make test');
    console.log('To install: sudo make install');
    
  } catch (error) {
    console.error(`\n❌ Build failed: ${error.message}`);
    process.exit(1);
  }
})();
