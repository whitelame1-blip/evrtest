# celery_tasks/celery_config.py
from celery import Celery
import os

celery_app = Celery(
    #'ielts_tasks', 
    broker='redis://127.0.0.1:6379/0', 
    backend='redis://127.0.0.1:6379/0',
    #include=['ai_backend', 'task'],
    #include=['voice', 'task']
)

# CRITICAL: Tell the worker to import your task file
# If task.py is in the root, use 'task'. 
# If it's in a folder, use 'folder_name.task'
celery_app.conf.update(
    imports=['task'],  # Add your task module here
    task_serializer='json',
    result_serializer='json',
    accept_content=['json']
)