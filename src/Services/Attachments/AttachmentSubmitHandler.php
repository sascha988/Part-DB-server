<?php
/**
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony).
 *
 * Copyright (C) 2019 - 2022 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published
 * by the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace App\Services\Attachments;

use App\Entity\Attachments\Attachment;
use App\Entity\Attachments\AttachmentContainingDBElement;
use App\Entity\Attachments\AttachmentType;
use App\Entity\Attachments\AttachmentTypeAttachment;
use App\Entity\Attachments\CategoryAttachment;
use App\Entity\Attachments\CurrencyAttachment;
use App\Entity\Attachments\LabelAttachment;
use App\Entity\Attachments\ProjectAttachment;
use App\Entity\Attachments\FootprintAttachment;
use App\Entity\Attachments\GroupAttachment;
use App\Entity\Attachments\ManufacturerAttachment;
use App\Entity\Attachments\MeasurementUnitAttachment;
use App\Entity\Attachments\PartAttachment;
use App\Entity\Attachments\StorelocationAttachment;
use App\Entity\Attachments\SupplierAttachment;
use App\Entity\Attachments\UserAttachment;
use App\Exceptions\AttachmentDownloadException;
use const DIRECTORY_SEPARATOR;
use function get_class;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypesInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * This service handles the form submitting of an attachment and handles things like file uploading and downloading.
 */
class AttachmentSubmitHandler
{
    protected AttachmentPathResolver $pathResolver;
    protected array $folder_mapping;
    protected bool $allow_attachments_downloads;
    protected HttpClientInterface $httpClient;
    protected MimeTypesInterface $mimeTypes;
    protected FileTypeFilterTools $filterTools;

    protected const BLACKLISTED_EXTENSIONS = ['php', 'phtml', 'php3', 'ph3', 'php4', 'ph4', 'php5', 'ph5', 'phtm', 'sh',
        'asp', 'cgi', 'py', 'pl', 'exe', 'aspx', 'js', 'mjs', 'jsp', 'css', 'jar', 'html', 'htm', 'shtm', 'shtml', 'htaccess',
        'htpasswd', ''];

    public function __construct(AttachmentPathResolver $pathResolver, bool $allow_attachments_downloads,
                                HttpClientInterface $httpClient, MimeTypesInterface $mimeTypes,
        FileTypeFilterTools $filterTools)
    {
        $this->pathResolver = $pathResolver;
        $this->allow_attachments_downloads = $allow_attachments_downloads;
        $this->httpClient = $httpClient;
        $this->mimeTypes = $mimeTypes;

        $this->filterTools = $filterTools;

        //The mapping used to determine which folder will be used for an attachment type
        $this->folder_mapping = [
            PartAttachment::class => 'part',
            AttachmentTypeAttachment::class => 'attachment_type',
            CategoryAttachment::class => 'category',
            CurrencyAttachment::class => 'currency',
            ProjectAttachment::class => 'project',
            FootprintAttachment::class => 'footprint',
            GroupAttachment::class => 'group',
            ManufacturerAttachment::class => 'manufacturer',
            MeasurementUnitAttachment::class => 'measurement_unit',
            StorelocationAttachment::class => 'storelocation',
            SupplierAttachment::class => 'supplier',
            UserAttachment::class => 'user',
            LabelAttachment::class => 'label_profile',
        ];
    }

    /**
     * Check if the extension of the uploaded file is allowed for the given attachment type.
     * Returns true, if the file is allowed, false if not.
     */
    public function isValidFileExtension(AttachmentType $attachment_type, UploadedFile $uploadedFile): bool
    {
        //Only validate if the attachment type has specified an filetype filter:
        if (empty($attachment_type->getFiletypeFilter())) {
            return true;
        }

        return $this->filterTools->isExtensionAllowed(
            $attachment_type->getFiletypeFilter(),
            $uploadedFile->getClientOriginalExtension()
        );
    }

    /**
     * Generates a filename for the given attachment and extension.
     * The filename contains a random id, so every time this function is called you get an unique name.
     *
     * @param Attachment $attachment The attachment that should be used for generating an attachment
     * @param string     $extension  The extension that the new file should have (must only contain chars allowed in pathes)
     *
     * @return string the new filename
     */
    public function generateAttachmentFilename(Attachment $attachment, string $extension): string
    {
        //Normalize extension
        $extension = transliterator_transliterate(
            'Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()',
            $extension
        );

        //Use the (sanatized) attachment name as an filename part
        $safeName = transliterator_transliterate(
            'Any-Latin; Latin-ASCII; [^A-Za-z0-9_] remove; Lower()',
            $attachment->getName()
        );

        return $safeName.'-'.uniqid('', false).'.'.$extension;
    }

    /**
     * Generates an (absolute) path to a folder where the given attachment should be stored.
     *
     * @param Attachment $attachment    The attachment that should be used for
     * @param bool       $secure_upload True if the file path should be located in a safe location
     *
     * @return string the absolute path for the attachment folder
     */
    public function generateAttachmentPath(Attachment $attachment, bool $secure_upload = false): string
    {
        if ($secure_upload) {
            $base_path = $this->pathResolver->getSecurePath();
        } else {
            $base_path = $this->pathResolver->getMediaPath();
        }

        //Ensure the given attachment class is known to mapping
        if (!isset($this->folder_mapping[get_class($attachment)])) {
            throw new InvalidArgumentException('The given attachment class is not known! The passed class was: '.get_class($attachment));
        }
        //Ensure the attachment has an assigned element
        if (null === $attachment->getElement()) {
            throw new InvalidArgumentException('The given attachment is not assigned to an element! An element is needed to generate a path!');
        }

        //Build path
        return
            $base_path.DIRECTORY_SEPARATOR //Base path
            .$this->folder_mapping[get_class($attachment)].DIRECTORY_SEPARATOR.$attachment->getElement()->getID();
    }

    /**
     * Handle the submit of an attachment form.
     * This function will move the uploaded file or download the URL file to server, if needed.
     *
     * @param Attachment        $attachment the attachment that should be used for handling
     * @param UploadedFile|null $file       If given, that file will be moved to the right location
     * @param array             $options    The options to use with the upload. Here you can specify that an URL should be downloaded,
     *                                      or an file should be moved to a secure location.
     *
     * @return Attachment The attachment with the new filename (same instance as passed $attachment)
     */
    public function handleFormSubmit(Attachment $attachment, ?UploadedFile $file, array $options = []): Attachment
    {
        $resolver = new OptionsResolver();
        $this->configureOptions($resolver);
        $options = $resolver->resolve($options);

        //When a file is given then upload it, otherwise check if we need to download the URL
        if ($file) {
            $this->upload($attachment, $file, $options);
        } elseif ($options['download_url'] && $attachment->isExternal()) {
            $this->downloadURL($attachment, $options);
        }

        //Move the attachment files to secure location (and back) if needed
        $this->moveFile($attachment, $options['secure_attachment']);

        //Rename blacklisted (unsecure) files to a better extension
        $this->renameBlacklistedExtensions($attachment);

        //Check if we should assign this attachment to master picture
        //this is only possible if the attachment is new (not yet persisted to DB)
        if ($options['become_preview_if_empty'] && null === $attachment->getID() && $attachment->isPicture()) {
            $element = $attachment->getElement();
            if ($element instanceof AttachmentContainingDBElement && null === $element->getMasterPictureAttachment()) {
                $element->setMasterPictureAttachment($attachment);
            }
        }

        return $attachment;
    }

    /**
     * Rename attachments with an unsafe extension (meaning files which would be runned by a  to a safe one.
     * @param Attachment $attachment
     * @return Attachment
     */
    protected function renameBlacklistedExtensions(Attachment $attachment): Attachment
    {
        //We can not do anything on builtins or external ressources
        if ($attachment->isBuiltIn() || $attachment->isExternal()) {
            return $attachment;
        }

        //Determine the old filepath
        $old_path = $this->pathResolver->placeholderToRealPath($attachment->getPath());
        if (empty($old_path) || !file_exists($old_path)) {
            return $attachment;
        }
        $filename = basename($old_path);
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));


        //Check if the extension is blacklisted and replace the file extension with txt if needed
        if(in_array($ext, self::BLACKLISTED_EXTENSIONS)) {
            $new_path = $this->generateAttachmentPath($attachment, $attachment->isSecure())
            .DIRECTORY_SEPARATOR.$this->generateAttachmentFilename($attachment, 'txt');

            //Move file to new directory
            $fs = new Filesystem();
            $fs->rename($old_path, $new_path);

            //Update the attachment
            $attachment->setPath($this->pathResolver->realPathToPlaceholder($new_path));
        }


        return $attachment;
    }

    protected function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            //If no preview image was set yet, the new uploaded file will become the preview image
            'become_preview_if_empty' => true,
            //When an URL is given download the URL
            'download_url' => false,
            'secure_attachment' => false,
        ]);
    }

    /**
     * Move the given attachment to secure location (or back to public folder) if needed.
     *
     * @param Attachment $attachment      the attachment for which the file should be moved
     * @param bool       $secure_location this value determines, if the attachment is moved to the secure or public folder
     *
     * @return Attachment The attachment with the updated filepath
     */
    protected function moveFile(Attachment $attachment, bool $secure_location): Attachment
    {
        //We can not do anything on builtins or external ressources
        if ($attachment->isBuiltIn() || $attachment->isExternal()) {
            return $attachment;
        }

        //Check if we need to move the file
        if ($secure_location === $attachment->isSecure()) {
            return $attachment;
        }

        //Determine the old filepath
        $old_path = $this->pathResolver->placeholderToRealPath($attachment->getPath());
        if (!file_exists($old_path)) {
            return $attachment;
        }

        $filename = basename($old_path);
        //If the basename is not one of the new unique on, we have to save the old filename
        if (!preg_match('#\w+-\w{13}\.#', $filename)) {
            //Save filename to attachment field
            $attachment->setFilename($attachment->getFilename());
        }

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $new_path = $this->generateAttachmentPath($attachment, $secure_location)
            .DIRECTORY_SEPARATOR.$this->generateAttachmentFilename($attachment, $ext);

        //Move file to new directory
        $fs = new Filesystem();
        //Ensure that the new path exists
        $fs->mkdir(dirname($new_path));
        $fs->rename($old_path, $new_path);

        //Save info to attachment entity
        $new_path = $this->pathResolver->realPathToPlaceholder($new_path);
        $attachment->setPath($new_path);

        return $attachment;
    }

    /**
     * Download the URL set in the attachment and save it on the server.
     *
     * @param array $options The options from the handleFormSubmit function
     *
     * @return Attachment The attachment with the new filepath
     */
    protected function downloadURL(Attachment $attachment, array $options): Attachment
    {
        //Check if we are allowed to download files
        if (!$this->allow_attachments_downloads) {
            throw new RuntimeException('Download of attachments is not allowed!');
        }

        $url = $attachment->getURL();

        $fs = new Filesystem();
        $attachment_folder = $this->generateAttachmentPath($attachment, $options['secure_attachment']);
        $tmp_path = $attachment_folder.DIRECTORY_SEPARATOR.$this->generateAttachmentFilename($attachment, 'tmp');

        try {
            $response = $this->httpClient->request('GET', $url, [
                'buffer' => false,
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new AttachmentDownloadException('Status code: '.$response->getStatusCode());
            }

            //Open a temporary file in the attachment folder
            $fs->mkdir($attachment_folder);
            $fileHandler = fopen($tmp_path, 'wb');
            //Write the downloaded data to file
            foreach ($this->httpClient->stream($response) as $chunk) {
                fwrite($fileHandler, $chunk->getContent());
            }
            fclose($fileHandler);

            //File download should be finished here, so determine the new filename and extension
            $headers = $response->getHeaders();
            //Try to determine an filename
            $filename = '';

            //If an content disposition header was set try to extract the filename out of it
            if (isset($headers['content-disposition'])) {
                $tmp = [];
                preg_match('/[^;\\n=]*=([\'\"])*(.*)(?(1)\1|)/', $headers['content-disposition'][0], $tmp);
                $filename = $tmp[2];
            }

            //If we dont know filename yet, try to determine it out of url
            if ('' === $filename) {
                $filename = basename(parse_url($url, PHP_URL_PATH));
            }

            //Set original file
            $attachment->setFilename($filename);

            //Check if we have a extension given
            $pathinfo = pathinfo($filename);
            if (!empty($pathinfo['extension'])) {
                $new_ext = $pathinfo['extension'];
            } else { //Otherwise we have to guess the extension for the new file, based on its content
                $new_ext = $this->mimeTypes->getExtensions($this->mimeTypes->guessMimeType($tmp_path))[0] ?? 'tmp';
            }

            //Rename the file to its new name and save path to attachment entity
            $new_path = $attachment_folder.DIRECTORY_SEPARATOR.$this->generateAttachmentFilename($attachment, $new_ext);
            $fs->rename($tmp_path, $new_path);

            //Make our file path relative to %BASE%
            $new_path = $this->pathResolver->realPathToPlaceholder($new_path);
            //Save the path to the attachment
            $attachment->setPath($new_path);
        } catch (TransportExceptionInterface $transportExceptionInterface) {
            throw new AttachmentDownloadException('Transport error!');
        }

        return $attachment;
    }

    /**
     * Moves the given uploaded file to a permanent place and saves it into the attachment.
     *
     * @param Attachment   $attachment The attachment in which the file should be saved
     * @param UploadedFile $file       The file which was uploaded
     * @param array        $options    The options from the handleFormSubmit function
     *
     * @return Attachment The attachment with the new filepath
     */
    protected function upload(Attachment $attachment, UploadedFile $file, array $options): Attachment
    {
        //Move our temporay attachment to its final location
        $file_path = $file->move(
            $this->generateAttachmentPath($attachment, $options['secure_attachment']),
            $this->generateAttachmentFilename($attachment, $file->getClientOriginalExtension())
        )->getRealPath();

        //Make our file path relative to %BASE%
        $file_path = $this->pathResolver->realPathToPlaceholder($file_path);
        //Save the path to the attachment
        $attachment->setPath($file_path);
        //And save original filename
        $attachment->setFilename($file->getClientOriginalName());

        return $attachment;
    }
}
