# Bundle Images & Scripts for On Prem Install

Rough draft of process for bundling images and scripts to run during install. This will result in 

## Build & Run the images (or Pull from Docker Hub) 
So we can bundle them

### Grant permissions & Run setup script:
To bundle the images
```bash
# run from the /offline directory under project root
cd offline && chmod ug+x setup/setup.sh && setup/setup.sh
```

When finished, the script will output the path to the zipped archive.

## Upload
Upload the zipped archive to S3 `porta-internal-plugins` bucket once finished.

!! **In S3, remember to:**
- "Make Public using ACL" (Under actions)
- Set user defined metadata of the version (Under actions)
