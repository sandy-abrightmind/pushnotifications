<?php

namespace Craft;

/**
 * Push Notifications - Notification Element Type.
 *
 * @author    Bob Olde Hampsink
 * @copyright Copyright (c) 2015, Bob Olde Hampsink
 * @license   MIT
 *
 * @link      https://github.com/boboldehampsink
 * @since     0.0.1
 */
class PushNotifications_NotificationElementType extends BaseElementType
{
    /**
     * Returns the element type name.
     *
     * @return string
     */
    public function getName()
    {
        return Craft::t('Push Notifications');
    }

    /**
     * Return true so we have a status select menu.
     *
     * @return bool
     */
    public function hasStatuses()
    {
        return true;
    }

    /**
     * Define statuses.
     *
     * @return array
     */
    public function getStatuses()
    {
        return array(
            PushNotifications_NotificationModel::SENT    => Craft::t('Sent'),
            PushNotifications_NotificationModel::PENDING => Craft::t('Pending'),
        );
    }

    /**
     * Returns whether this element type has content.
     *
     * @return bool
     */
    public function hasContent()
    {
        return false;
    }

    /**
     * Returns whether this element type has titles.
     *
     * @return bool
     */
    public function hasTitles()
    {
        return false;
    }

    /**
     * Returns this element type's sources.
     *
     * @param string|null $context
     *
     * @return array|false
     */
    public function getSources($context = null)
    {
        $user = craft()->userSession->getUser();

        if($user->can('admin'))
        {
            $sources = array(
                '*' => array(
                    'label'    => Craft::t('All notifications'),
                ),
            );
        }
        else {
            // only display the notifications for the apps that the user has access to

            // get list of app ids that the user has access to
            $myIds = array();

            foreach(craft()->pushNotifications_apps->getAllApps() as $app)
            {
                $myIds[] = $app->id;
            }

            $sources = array(
                '*' => array(
                    'label' => Craft::t('All notifications'),
                    'criteria' => array('appId' => $myIds),
                ),
            );
        }

        foreach (craft()->pushNotifications_apps->getAllApps() as $app) {
            $key = 'app:'.$app->id;

            $sources[$key] = array(
                'label'    => $app->name,
                'criteria' => array('appId' => $app->id),
            );
        }

        return $sources;
    }

    /**
     * Returns the attributes that can be shown/sorted by in table views.
     *
     * @param string|null $source
     *
     * @return array
     */
    public function defineTableAttributes($source = null)
    {
        return array(
            'title'         => Craft::t('Title'),
            'body'          => Craft::t('Body'),
            'command'       => Craft::t('Command'),
            'schedule'      => Craft::t('Send Date'),
        );
    }

    /**
     * Returns the table view HTML for a given attribute.
     *
     * @param BaseElementModel $element
     * @param string           $attribute
     *
     * @return string
     */
    public function getTableAttributeHtml(BaseElementModel $element, $attribute)
    {
        switch ($attribute) {

            case 'body':
                return strlen($element->$attribute) > 50 ? substr($element->$attribute, 0, 50).'...' : $element->$attribute;
                break;

            case 'command':
                $app = $element->getApp();
                foreach ($app->commands as $command) {
                    if ($command['param'] == $element->$attribute) {
                        return $command['name'];
                    }
                }
                break;

            default:
                return parent::getTableAttributeHtml($element, $attribute);
                break;

        }
    }

    /**
     * Defines any custom element criteria attributes for this element type.
     *
     * @return array
     */
    public function defineCriteriaAttributes()
    {
        return array(
            'app'       => AttributeType::Mixed,
            'appId'     => AttributeType::Mixed,
            'title'     => AttributeType::Name,
            'body'      => AttributeType::String,
            'command'   => AttributeType::String,
            'schedule'  => AttributeType::DateTime,
            'status'    => AttributeType::String,
            'order'     => array(AttributeType::String, 'default' => 'pushnotifications_notifications.id desc'),
        );
    }

    /**
     * @inheritDoc IElementType::getElementQueryStatusCondition()
     *
     * @param DbCommand $query
     * @param string    $status
     *
     * @return array|false|string|void
     */
    public function getElementQueryStatusCondition(DbCommand $query, $status)
    {
        $currentTimeDb = DateTimeHelper::currentTimeForDb();

        switch ($status) {

            case PushNotifications_NotificationModel::SENT:
                return array(
                    "or",
                    "pushnotifications_notifications.schedule IS NULL",
                    "pushnotifications_notifications.schedule <= '{$currentTimeDb}'"
                );
                break;

            case PushNotifications_NotificationModel::PENDING:
                return "pushnotifications_notifications.schedule > '{$currentTimeDb}'";
                break;
        }
    }

    /**
     * Modifies an element query targeting elements of this type.
     *
     * @param DbCommand            $query
     * @param ElementCriteriaModel $criteria
     *
     * @return mixed
     */
    public function modifyElementsQuery(DbCommand $query, ElementCriteriaModel $criteria)
    {

        $query
            ->addSelect('pushnotifications_notifications.appId, pushnotifications_notifications.title, pushnotifications_notifications.body, pushnotifications_notifications.command, pushnotifications_notifications.schedule')
            ->join('pushnotifications_notifications pushnotifications_notifications', 'pushnotifications_notifications.id = elements.id');

        if ($criteria->appId) {
            $query->andWhere(DbHelper::parseParam('pushnotifications_notifications.appId', $criteria->appId, $query->params));
        }

        if ($criteria->app) {
            $query->join('pushnotifications_apps pushnotifications_apps', 'pushnotifications_apps.id = pushnotifications_notifications.appId');
            $query->andWhere(DbHelper::parseParam('pushnotifications_apps.handle', $criteria->app, $query->params));
        }

        if ($criteria->title) {
            $query->andWhere(DbHelper::parseParam('pushnotifications_notifications.title', $criteria->title, $query->params));
        }

        if ($criteria->body) {
            $query->andWhere(DbHelper::parseParam('pushnotifications_notifications.body', $criteria->body, $query->params));
        }

        if ($criteria->command) {
            $query->andWhere(DbHelper::parseParam('pushnotifications_notifications.command', $criteria->command, $query->params));
        }

        if ($criteria->schedule) {
            $query->andWhere(DbHelper::parseDateParam('pushnotifications_notifications.schedule', $criteria->schedule, $query->params));
        }
    }

    /**
     * Populates an element model based on a query result.
     *
     * @param array $row
     *
     * @return array
     */
    public function populateElementModel($row)
    {
        return PushNotifications_NotificationModel::populateModel($row);
    }
}
